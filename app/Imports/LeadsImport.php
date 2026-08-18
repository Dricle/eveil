<?php

namespace App\Imports;

use App\Enums\EmailSource;
use App\Enums\SuppressionLayer;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Suppression;
use App\Support\Url;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

/**
 * A list somebody already had, turned into leads.
 *
 * The rule that shapes everything here: a row is worth keeping when it carries
 * an email OR a LinkedIn URL. Refusing a LinkedIn-only row would throw away the
 * half of a hand-built list that a later channel can still reach, and the schema
 * already allows a lead with no address.
 *
 * Nothing is imported silently. Every row that does not land comes back with
 * the line number and the reason, because "412 of 500 imported" with no list is
 * a support ticket rather than a result.
 *
 * Reading the file is `maatwebsite/excel`'s job — it also normalises the
 * heading row (`First Name` becomes `first_name`), strips the BOM Excel writes,
 * and reads xlsx the day somebody uploads one. What is left here is the only
 * part that is ours: deciding what each row means.
 *
 * `ToModel` would be the shorter concern and cannot express this: a row that is
 * rejected, a row that is a duplicate and a row that becomes a lead are three
 * outcomes, and only one of them is a model.
 */
class LeadsImport implements OnEachRow, SkipsEmptyRows, WithHeadingRow
{
    /**
     * The columns the template ships with. Anything else in the file is
     * ignored rather than rejected — people add their own notes column.
     */
    public const COLUMNS = [
        'email', 'first_name', 'last_name', 'title', 'linkedin_url', 'company_name', 'company_domain',
    ];

    /**
     * ponytail: an import runs inside the request, so it is bounded rather than
     * queued. Past this the answer is a job with a progress screen, not a
     * bigger number.
     */
    private const MAX_ROWS = 5_000;

    private int $imported = 0;

    private int $duplicates = 0;

    /** @var array<int, array{line: int, value: string, reason: string}> */
    private array $rejected = [];

    /** @var array<string, true> */
    private array $seen = [];

    private bool $truncated = false;

    public function __construct(private Project $project) {}

    /**
     * What happened to every row, once the reader has been through the file.
     *
     * @return array{imported: int, duplicates: int, rejected: array<int, array{line: int, value: string, reason: string}>, rejected_count: int, truncated: bool}
     */
    public function report(): array
    {
        return [
            'imported' => $this->imported,
            'duplicates' => $this->duplicates,
            // The whole list would be a wall of text on a bad file; the count
            // beside it is what says how bad.
            'rejected' => array_slice($this->rejected, 0, 50),
            'rejected_count' => count($this->rejected),
            'truncated' => $this->truncated,
        ];
    }

    public function onRow(Row $row): void
    {
        if ($this->truncated || $this->imported + $this->duplicates + count($this->rejected) >= self::MAX_ROWS) {
            $this->truncated = true;

            return;
        }

        /** @var array<string, mixed> $values */
        $values = $row->toArray();

        $email = mb_strtolower(trim((string) ($values['email'] ?? '')));
        $linkedin = trim((string) ($values['linkedin_url'] ?? ''));

        // The row number the person sees in their spreadsheet, heading row
        // included — anything else makes the report useless to check against.
        $line = $row->getIndex();
        $reason = $this->reject($email, $linkedin);

        if ($reason !== null) {
            $this->rejected[] = ['line' => $line, 'value' => $email !== '' ? $email : $linkedin, 'reason' => $reason];

            return;
        }

        $this->seen[$this->key($email, $linkedin)] = true;

        if ($this->exists($email, $linkedin)) {
            $this->duplicates++;

            return;
        }

        $this->store($values, $email, $linkedin);
        $this->imported++;
    }

    private function key(string $email, string $linkedin): string
    {
        return $email !== '' ? 'e:'.$email : 'l:'.mb_strtolower($linkedin);
    }

    /**
     * Why this row cannot be kept, or null when it can.
     */
    private function reject(string $email, string $linkedin): ?string
    {
        if ($email === '' && $linkedin === '') {
            return 'Neither an email address nor a LinkedIn URL.';
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Not an email address.';
        }

        if ($linkedin !== '' && $email === '' && Url::normalize($this->withScheme($linkedin)) === null) {
            return 'Not a usable LinkedIn URL.';
        }

        if (isset($this->seen[$this->key($email, $linkedin)])) {
            return 'Appears twice in this file.';
        }

        if ($email !== '' && $this->wasErased($email)) {
            // A request to be forgotten outlives the data it destroyed. Letting
            // a file put her back is exactly the hole the tombstone exists to
            // close.
            return 'This person asked to be forgotten.';
        }

        if ($email !== '' && $this->isSuppressed($email)) {
            return 'On the suppression list.';
        }

        return null;
    }

    private function wasErased(string $email): bool
    {
        return Lead::query()
            ->where('project_id', $this->project->id)
            ->where('email_hash', Lead::hashFor($email))
            ->whereNotNull('erased_at')
            ->exists();
    }

    private function isSuppressed(string $email): bool
    {
        $domain = Str::after($email, '@');

        return Suppression::query()
            ->where(fn ($query) => $query->where('email', $email)->orWhere('domain', $domain))
            ->where(fn ($query) => $query
                ->where('layer', SuppressionLayer::Toxic)
                ->orWhere('project_id', $this->project->id)
                ->orWhere('organization_id', $this->project->organization_id))
            ->exists();
    }

    private function exists(string $email, string $linkedin): bool
    {
        $query = Lead::query()->where('project_id', $this->project->id);

        return $email !== ''
            ? $query->where('email_hash', Lead::hashFor($email))->exists()
            : $query->where('linkedin_url', $linkedin)->exists();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function store(array $values, string $email, string $linkedin): void
    {
        Lead::create([
            'project_id' => $this->project->id,
            'company_id' => $this->company($values)?->id,
            'first_name' => $this->text($values, 'first_name'),
            'last_name' => $this->text($values, 'last_name'),
            'title' => $this->text($values, 'title'),
            'email' => $email === '' ? null : $email,
            // Deliberately unverified: verifying inline would mean a DNS lookup
            // and an SMTP probe per row, with the person watching a spinner.
            // The pre-send check is where an address has to prove itself.
            'email_status' => null,
            'email_source' => $email === '' ? null : EmailSource::Imported,
            'linkedin_url' => $linkedin === '' ? null : $linkedin,
            'source' => 'import',
            'discovered_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function company(array $values): ?Company
    {
        $written = $this->text($values, 'company_domain');
        $domain = $written === null ? null : Url::host($this->withScheme($written));

        // ponytail: `companies.domain` is NOT NULL, so a company named without
        // one cannot be stored and the lead simply carries no company. Drop
        // this branch when site-less companies become storable.
        if ($domain === null || $domain === '') {
            return null;
        }

        return Company::firstOrCreate(
            ['project_id' => $this->project->id, 'domain' => mb_strtolower($domain)],
            [
                'name' => $this->text($values, 'company_name') ?? $domain,
                'source' => 'import',
                'discovered_at' => now(),
            ],
        );
    }

    /**
     * People write `acme.com` as often as `https://www.acme.com/`, and both
     * mean the same site. Everything downstream parses URLs, so give it one.
     */
    private function withScheme(string $value): string
    {
        return str_contains($value, '://') ? $value : 'https://'.$value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function text(array $values, string $key): ?string
    {
        $value = trim((string) ($values[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}

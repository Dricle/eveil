<?php

namespace App\Services\Outreach;

use App\Models\EmailAccount;
use App\Models\Project;

/**
 * The last thing a written mail meets before it leaves, and none of it is a
 * model call.
 *
 * The personaliser is told not to leave a `{{first_name}}` behind or link to
 * somebody else's site, and an instruction is a floor, not a guarantee. One
 * "Hi {{first_name}}" under the user's own name costs more than the lead: it
 * tells the recipient a machine wrote it, which is exactly what ADR-029 exists
 * to prevent. So the mail is read here, in code, and a mail that fails is held
 * back rather than sent.
 *
 * The signature is appended later by `Sender`, so it is never judged here: the
 * user wrote it, and its links are theirs to choose.
 */
class PreSendCheck
{
    /**
     * Generous on purpose: a cold mail runs to about a hundred words, and this
     * only catches a model that rambled, never a user who wrote long.
     */
    public const MAX_BODY_WORDS = 400;

    /**
     * Placeholder shapes a template or a model leaves behind: `{{first_name}}`,
     * `{company}`, `[First Name]`, `[Company]`.
     */
    private const PLACEHOLDER = '/\{\{[^}]*\}\}|\{\s*[a-z_ ]{2,30}\s*\}|\[\s*(?:first|last|full|your|company|prospect|contact|recipient)?\s*_?\s*(?:name|company|city|title|role|website)\s*\]/iu';

    /**
     * What is wrong with this mail, in words a user can act on. Empty means it
     * may leave.
     *
     * @return list<string>
     */
    public function problems(string $subject, string $body, EmailAccount $account, Project $project): array
    {
        $problems = [];

        if (mb_trim($subject) === '') {
            $problems[] = 'the subject is empty';
        }

        if (mb_trim($body) === '') {
            return [...$problems, 'the body is empty'];
        }

        if (preg_match(self::PLACEHOLDER, $subject.' '.$body, $match)) {
            $problems[] = 'an unfilled placeholder is left in: '.$match[0];
        }

        $foreign = $this->foreignLinkDomains($body, $this->allowedDomains($account, $project));

        if ($foreign !== []) {
            $problems[] = 'it links to a site that is not yours: '.implode(', ', $foreign);
        }

        if (str_word_count(strip_tags($body)) > self::MAX_BODY_WORDS) {
            $problems[] = 'the body runs past '.self::MAX_BODY_WORDS.' words';
        }

        return $problems;
    }

    /**
     * The sender's own domain and the product's: the only two places a link in
     * a mail typed by hand would plausibly point.
     *
     * @return list<string>
     */
    private function allowedDomains(EmailAccount $account, Project $project): array
    {
        $domains = [mb_strtolower((string) str($account->from_email)->after('@'))];

        $host = parse_url((string) $project->url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $domains[] = $this->bare($host);
        }

        return array_values(array_filter($domains));
    }

    /**
     * Hosts of every `http(s)://` or `www.` link whose domain is neither an
     * allowed one nor a subdomain of one. Bare `example.com` text is left
     * alone: "Node.js" is not a link, and guessing would block good mail.
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function foreignLinkDomains(string $body, array $allowed): array
    {
        preg_match_all('~\b(?:https?://|www\.)([a-z0-9.-]+\.[a-z]{2,})~iu', $body, $matches);

        $foreign = [];

        foreach ($matches[1] as $host) {
            $host = $this->bare(mb_strtolower(mb_rtrim($host, '.')));

            $isAllowed = collect($allowed)->contains(
                fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain),
            );

            if (! $isAllowed) {
                $foreign[$host] = $host;
            }
        }

        return array_values($foreign);
    }

    private function bare(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }
}

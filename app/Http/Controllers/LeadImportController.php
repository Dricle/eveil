<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadImportRequest;
use App\Imports\LeadsImport;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A list somebody already had. The template says what the columns are, and the
 * report says what happened to every row that did not land.
 */
class LeadImportController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    /**
     * The template, so nobody has to guess the column names. Generated from the
     * same constant the parser reads, which is what keeps the two in step.
     */
    public function show(): Response
    {
        $example = [
            'jean@example.com', 'Jean', 'Martin', 'Operations manager',
            'https://www.linkedin.com/in/example', 'Example', 'example.com',
        ];

        $csv = implode(',', LeadsImport::COLUMNS)."\n".implode(',', $example)."\n"
            .",,,,https://www.linkedin.com/in/no-address-known,Other,other.example\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="eveil-contacts-template.csv"',
        ]);
    }

    public function store(LeadImportRequest $request): RedirectResponse
    {
        $import = new LeadsImport($this->currentProject->getOrFail());

        Excel::import($import, $request->file('file'));

        // Flashed rather than rendered here: the result belongs on the list it
        // just changed, and a refresh should not show it again.
        return to_route('contacts.index')->with('import', $import->report());
    }
}

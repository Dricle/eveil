<x-marketing-layout
    :title="config('app.name') . ' - Data retention'"
    description="How long Eveil's hosted edition keeps prospect data, reply threads and account data, and how erasure works."
>
    <x-marketing-legal
        heading="Data retention"
        meta="Last updated: [publication date] · Periods below are Eveil's own policy, not a third-party standard"
        notice="Draft. The periods and mechanics below are accurate to the current codebase. Confirm the enforcement status in section 2 before publishing, and fill in the closed-account row once that policy is decided."
    >
        <x-slot:toc>
            <a href="#defaults" style="color:rgba(232,236,242,.7);font-size:14px">1. Default periods</a>
            <a href="#purge" style="color:rgba(232,236,242,.7);font-size:14px">2. Enforcement</a>
            <a href="#erasure" style="color:rgba(232,236,242,.7);font-size:14px">3. Erasure and the hash</a>
            <a href="#suppression" style="color:rgba(232,236,242,.7);font-size:14px">4. Suppression layers</a>
            <a href="#operator" style="color:rgba(232,236,242,.7);font-size:14px">5. Operator tuning</a>
        </x-slot:toc>

        <h2 id="defaults" class="legal-h2" style="margin-bottom:16px">1. Default periods</h2>
        <div style="border:1px solid rgba(232,236,242,.1);border-radius:12px;overflow:hidden;margin-bottom:32px">
            <table style="width:100%;border-collapse:collapse;font-size:14.5px;text-align:left">
                <thead>
                    <tr style="background:rgba(232,236,242,.04)">
                        <th style="padding:12px 16px;font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Data</th>
                        <th style="padding:12px 16px;font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Kept for</th>
                        <th style="padding:12px 16px;font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Then</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Uncontacted prospects</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);font-family:'Geist Mono',monospace;font-size:13px;color:#6fd3ec">6 months</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Deleted outright</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Contacted prospects</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);font-family:'Geist Mono',monospace;font-size:13px;color:#6fd3ec">3 years after last contact</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Deleted outright</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Reply threads</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);font-family:'Geist Mono',monospace;font-size:13px;color:#6fd3ec">Tied to the lead</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Subject and body cleared when the lead is</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">AI agent run payloads (input/output)</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);font-family:'Geist Mono',monospace;font-size:13px;color:#6fd3ec">90 days</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Cleared; run metadata (model, duration, cost) stays</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Suppression entries</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);font-family:'Geist Mono',monospace;font-size:13px;color:#6fd3ec">Indefinite</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Never removed; checked before every send</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Closed accounts</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);font-family:'Geist Mono',monospace;font-size:13px;color:#6fd3ec">[not yet decided]</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">[not yet decided]</td></tr>
                </tbody>
            </table>
        </div>

        <h2 id="purge" class="legal-h2">2. Enforcement</h2>
        <p class="legal-p" style="margin-bottom:14px">Erasing one specific person, whether from their own reply of "STOP" or from a direct request to us, takes effect immediately and does not wait for a schedule: see section 3. That part of this page is already true of every lead in the system today.</p>
        <p class="legal-p">The time-based sweep that applies the 6-month, 3-year, and 90-day windows above to everything else is Eveil's committed retention policy. Eveil is an early-stage, actively developed product, in the spirit of the "Status" note on its own README: if the scheduled job that enforces this sweep automatically has not shipped yet on the version you are running, these periods are the ceiling we are building toward, not yet a background process you can observe. Check the project's GitHub issues for its current state before relying on it as fact rather than policy.</p>

        <h2 id="erasure" class="legal-h2">3. Erasure and the hash</h2>
        <p class="legal-p" style="margin-bottom:14px">Erasing a lead clears their name, email address, job title, LinkedIn URL, and source page, and blanks the subject and body of every message sent to or received from them. The row itself is not deleted: what remains is a one-way hash of their email address and the date they were erased, kept specifically so that person can never be re-added and re-contacted, even by a later CSV import or a fresh discovery run turning up the same page. The hash cannot be reversed back into an address.</p>
        <p class="legal-p">Erasure is scoped to the one person: it never deletes or hides the company they belonged to, or any other contact at that company, since one person asking to be forgotten is not their employer asking to be removed from consideration.</p>

        <h2 id="suppression" class="legal-h2">4. Suppression layers</h2>
        <p class="legal-p" style="margin-bottom:14px">An opt-out is enforced at one of three layers, depending on what triggered it: a "STOP" reply suppresses that address for the project it was sent to; a hard bounce suppresses it for the mailbox that sent it, since the address is what failed to deliver, not the project; and an address flagged as a spam trap or otherwise toxic is suppressed instance-wide, fed only by public signals never by anything a customer's prospect did.</p>
        <p class="legal-p">Two situations escalate suppression further: a spam complaint suppresses the address across the whole organization, not just the one project it complained about, and the same address replying "STOP" to two different projects inside one organization does the same. In both cases the wider suppression is permanent and is never undone by a later import.</p>

        <h2 id="operator" class="legal-h2">5. Operator tuning</h2>
        <p class="legal-p" style="margin-bottom:40px">On a self-hosted instance, these periods are not yet adjustable from a settings screen: there is no operator-tuning control for them today. If you need different values, they are fixed in the application's source rather than the database, so changing them means changing the code that reads them until a tuning screen ships.</p>

        <x-slot:links>
            <a href="{{ route('privacy') }}">Privacy policy</a>
            <a href="{{ route('terms') }}">Terms of service</a>
            <a href="{{ route('contact') }}">Contact us</a>
        </x-slot:links>
    </x-marketing-legal>
</x-marketing-layout>

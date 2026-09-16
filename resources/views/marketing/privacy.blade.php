<x-marketing-layout
    :title="config('app.name') . ' - Privacy policy'"
    description="How Eveil's hosted edition handles account data, prospect data, mailbox credentials and AI processing."
>
    <x-marketing-legal
        heading="Privacy policy"
        meta="Last updated: [publication date] · Controller: DRICLE LLP (OC453390), 5 Brayford Square, London E1 0SG, United Kingdom"
        notice="Draft. Written from the actual product and data model, but not reviewed by a lawyer. Confirm the supervisory authority and response deadlines against your jurisdiction before publishing."
    >
        <x-slot:toc>
            <a href="#collect" style="color:rgba(232,236,242,.7);font-size:14px">1. What we collect</a>
            <a href="#prospects" style="color:rgba(232,236,242,.7);font-size:14px">2. Prospect data</a>
            <a href="#mailboxes" style="color:rgba(232,236,242,.7);font-size:14px">3. Mailbox credentials</a>
            <a href="#ai" style="color:rgba(232,236,242,.7);font-size:14px">4. AI processing</a>
            <a href="#subprocessors" style="color:rgba(232,236,242,.7);font-size:14px">5. Subprocessors</a>
            <a href="#rights" style="color:rgba(232,236,242,.7);font-size:14px">6. Your rights</a>
            <a href="#selfhost" style="color:rgba(232,236,242,.7);font-size:14px">7. Self-hosted instances</a>
        </x-slot:toc>

        <h2 id="collect" class="legal-h2">1. What we collect</h2>
        <p class="legal-p" style="margin-bottom:14px">At signup we collect your name, email address, a hashed password, the organization name you give, and the projects and role you hold inside it. This is what an account is: nothing here is optional, and it exists to let you log in and to attribute actions to a person.</p>
        <p class="legal-p">Using the product creates operational records tied to your organization: sending logs, credit ledger entries, and the input and output of each AI agent run (site analysis, target profile derivation, company qualification, contact extraction, sequence writing, reply handling). Agent run payloads, which can contain prospect names and email addresses, are treated as short lived: Eveil's policy is to clear them 90 days after the run. Agent run metrics (which model ran, how long, how many tokens, whether it succeeded) hold no personal data and are kept indefinitely, since they feed billing history. See <a href="{{ route('data-retention') }}">Data retention</a> for the full table and its current enforcement status.</p>

        <h2 id="prospects" class="legal-h2">2. Prospect data</h2>
        <p class="legal-p" style="margin-bottom:14px">Companies and contacts are found and read live at qualification time, over a bundled search engine and the company's own public pages. Nothing here is a purchased or scraped contact database. For the prospects you choose to contact, you (or your organization) are the data controller; Eveil processes that data on your instructions, as a processor, and the disclosure obligation toward the person contacted (for example under GDPR Article 14) sits with you.</p>
        <p class="legal-p">A reply of "STOP", or any equivalent opt-out, is recorded permanently in a suppression list and checked before every future send. When someone asks to be forgotten, or you erase a lead yourself, their name, email address, and any other identifying fields are cleared from the record, and the subject and body of every message tied to them are blanked. What survives is a one-way hash of the email address, kept specifically so that person can never be re-added and re-contacted, even by a later import or discovery run. The hash cannot be reversed into an address. Details of how this is scoped and enforced are in <a href="{{ route('data-retention') }}">Data retention</a>.</p>

        <h2 id="mailboxes" class="legal-h2">3. Mailbox credentials</h2>
        <p class="legal-p">SMTP and IMAP credentials for the mailbox you connect are encrypted at rest with a dedicated encryption key, held separately from the key that protects sessions and cookies, so that rotating one never touches the other. Nobody at Eveil reads these credentials in the course of normal operation; they exist only for the application to send and read mail on your behalf. Message bodies fetched over IMAP are stored, not just their metadata, for the threads a campaign actually touches, so replies can be threaded and shown to you; they are cleared when the lead they belong to is erased.</p>

        <h2 id="ai" class="legal-h2">4. AI processing</h2>
        <p class="legal-p" style="margin-bottom:14px">The hosted edition's agents currently run on Anthropic's Claude models. Site content, target profile drafts, company and contact information, and message drafts are sent to Anthropic's API to be processed and are subject to Anthropic's own commercial API terms, under which API inputs are not used to train their models by default. If that changes, or if we add or switch providers, this section will say so before it takes effect.</p>
        <p class="legal-p">On a self-hosted instance, the operator supplies their own AI provider key and chooses the provider and model for each agent; nothing is sent to Eveil or to any provider we choose on the operator's behalf. Provider keys are encrypted at rest the same way mailbox credentials are.</p>

        <h2 id="subprocessors" class="legal-h2">5. Subprocessors</h2>
        <div style="border:1px solid rgba(232,236,242,.1);border-radius:12px;overflow:hidden;margin-bottom:16px">
            <table style="width:100%;border-collapse:collapse;font-size:14.5px;text-align:left">
                <thead>
                    <tr style="background:rgba(232,236,242,.04)">
                        <th style="padding:12px 16px;font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Subprocessor</th>
                        <th style="padding:12px 16px;font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Purpose</th>
                        <th style="padding:12px 16px;font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Region</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">[Infrastructure host]</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Application hosting and backups</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">[region]</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Stripe</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Payments, invoices, and saved payment methods. Card details are held by Stripe; Eveil stores only a customer and payment method reference</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">EU / US</td></tr>
                    <tr><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08)">Anthropic</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">AI agent inference (site analysis, targeting, writing, reply handling)</td><td style="padding:13px 16px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">US</td></tr>
                </tbody>
            </table>
        </div>
        <p class="legal-p">Not listed because they are not third parties: the bundled search engine and page cache run as part of the Eveil infrastructure itself, not an outside vendor. Outreach mail is sent and read over the mailbox you connect, directly over SMTP and IMAP, with no relay in between.</p>

        <h2 id="rights" class="legal-h2">6. Your rights</h2>
        <p class="legal-p">Where applicable law grants them, you have the right to access, rectify, erase, or port your personal data, and to object to how it is processed. Send a request to <a href="mailto:privacy@eveil.cloud">privacy@eveil.cloud</a>; we respond within the deadline your law sets (for example, one month under the GDPR, extendable once for complex requests). If you are not satisfied with our response, you may complain to your local data protection supervisory authority; for a request concerning us as controller, that is the UK Information Commissioner's Office.</p>

        <h2 id="selfhost" class="legal-h2">7. Self-hosted instances</h2>
        <p class="legal-p" style="margin-bottom:40px">This policy covers the eveil.cloud hosted service only. If you run the AGPL-3.0 source on your own infrastructure instead, none of your instance's data passes through us: you are the controller for everything it stores, and this policy does not apply to it.</p>

        <x-slot:links>
            <a href="{{ route('terms') }}">Terms of service</a>
            <a href="{{ route('data-retention') }}">Data retention</a>
            <a href="{{ route('contact') }}">Contact us</a>
        </x-slot:links>
    </x-marketing-legal>
</x-marketing-layout>

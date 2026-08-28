<x-marketing-layout
    :title="config('app.name') . ' — Terms of service'"
    description="The terms governing the hosted eveil.cloud service: accounts, acceptable use, credits and licence."
>
    <x-marketing-legal
        heading="Terms of service"
        meta="Last updated: [publication date] · Provider: DRICLE LLP (OC453390), 5 Brayford Square, London E1 0SG, United Kingdom · Governing law: England and Wales"
        notice="Draft. Written from the actual product and billing model, but not reviewed by a lawyer. The liability and availability section in particular needs counsel before publishing."
    >
        <x-slot:toc>
            <a href="#service" style="color:rgba(232,236,242,.7);font-size:14px">1. The service</a>
            <a href="#accounts" style="color:rgba(232,236,242,.7);font-size:14px">2. Accounts and roles</a>
            <a href="#acceptable" style="color:rgba(232,236,242,.7);font-size:14px">3. Acceptable use</a>
            <a href="#credits" style="color:rgba(232,236,242,.7);font-size:14px">4. Credits and payment</a>
            <a href="#trial" style="color:rgba(232,236,242,.7);font-size:14px">5. Trial limits</a>
            <a href="#availability" style="color:rgba(232,236,242,.7);font-size:14px">6. Availability</a>
            <a href="#licence" style="color:rgba(232,236,242,.7);font-size:14px">7. Licence and source</a>
            <a href="#termination" style="color:rgba(232,236,242,.7);font-size:14px">8. Termination</a>
        </x-slot:toc>

        <h2 id="service" class="legal-h2">1. The service</h2>
        <p class="legal-p">eveil.cloud is the hosted edition of Eveil, an outreach sequencer: given your product's URL, its agents read the site, work out who buys the product, find matching companies and contacts, write an outreach sequence, send it, and read the replies. Depending on the autonomy level you choose, each of these steps waits for your approval or runs on its own. Whatever runs on its own still runs from your own connected mailbox: you remain responsible for every message it sends, exactly as if you had sent it yourself, and for having a lawful basis to contact the people it finds.</p>

        <h2 id="accounts" class="legal-h2">2. Accounts and roles</h2>
        <p class="legal-p">An account is created with a name, an email address, and a password; two-factor authentication (TOTP) is available and we recommend enabling it. There is no sign-in through a third-party identity provider. Access is split into three separate scopes: instance (superadmin settings, on self-hosted only), organization (billing and membership), and project (the outreach itself). If you invite members to your organization or a project, you are responsible for what they do with the access you grant them.</p>

        <h2 id="acceptable" class="legal-h2">3. Acceptable use</h2>
        <p class="legal-p" style="margin-bottom:14px">You may not use Eveil to send unlawful, deceptive, or misleadingly attributed messages, to contact people in jurisdictions where you have no lawful basis to do so, to ignore an opt-out, or to import a purchased or scraped contact list in place of the discovery the product performs. Beyond these rules, the product enforces some of its own limits automatically:</p>
        <ul style="margin:0 0 32px;padding-left:20px;color:rgba(232,236,242,.68);display:grid;gap:7px">
            <li>A reply of "STOP", however phrased, is final and is enforced at the level of the mailbox or organization it was sent to, not just the one campaign.</li>
            <li>Circuit breakers may pause a campaign automatically on a high bounce rate, spam complaints, a high negative reply rate, or repeated authentication failures. A pause like this is the product working as intended, not a service failure.</li>
            <li>A spam complaint, or the same address opting out across two of your projects, can escalate suppression to your whole organization rather than the one project involved.</li>
        </ul>

        <h2 id="credits" class="legal-h2">4. Credits and payment</h2>
        <p class="legal-p" style="margin-bottom:14px">Credits are prepaid at one published flat rate, consumed as actions run (site analysis, target profile derivation, discovery, company qualification, contact extraction, sequence generation, per-lead personalisation, and reply handling each cost a fixed number of credits; email verification, sending, and reading replies never cost credits). Credits do not expire and are not refundable once consumed. You may enable automatic top-up at a threshold you set, which authorizes an off-session charge to your saved payment method when your balance crosses it.</p>
        <p class="legal-p">Payment methods, invoices, and checkout are handled entirely by Stripe; we do not receive or store your card details, only a reference to the payment method Stripe holds on file. Published rates may change with notice; a change never retroactively reprices credits you have already bought.</p>

        <h2 id="trial" class="legal-h2">5. Trial limits</h2>
        <p class="legal-p">New organizations on the cloud edition start with a trial grant of credits, capped to one project and to a limited number of leads discovered, independent of how many credits remain. CSV export of leads is not available on a trial account until a first payment is made.</p>

        <h2 id="availability" class="legal-h2">6. Availability and liability</h2>
        <p class="legal-p">The service is provided without an uptime guarantee unless we agree to one with you separately in writing. We are not responsible for an outage or degraded behavior caused by a third-party AI provider, payment processor, or other service Eveil depends on. [Placeholder: limitation of liability and exclusion of indirect damages clause, to be drafted with counsel for your jurisdiction.]</p>

        <h2 id="licence" class="legal-h2">7. Licence and source</h2>
        <p class="legal-p">The Eveil application source is published under the GNU Affero General Public License v3.0 and may be self-hosted freely, including by a competitor. These terms govern the hosted eveil.cloud service only; nothing here restricts, or is intended to restrict, the rights the AGPL-3.0 grants you over the source code.</p>

        <h2 id="termination" class="legal-h2">8. Termination</h2>
        <p class="legal-p" style="margin-bottom:40px">You may delete your account at any time from your account settings; doing so deletes any organization that account solely owns, along with its projects. Deleting your account forfeits any unused credit balance, consistent with credits being non-refundable once purchased. We may suspend or terminate an account that breaches section 3. [Placeholder: confirm the export window, if any, between a deletion request and data being purged, and whether that window differs from the periods in our <a href="{{ route('data-retention') }}">Data retention</a> page.]</p>

        <x-slot:links>
            <a href="{{ route('privacy') }}">Privacy policy</a>
            <a href="{{ route('data-retention') }}">Data retention</a>
            <a href="{{ route('contact') }}">Contact us</a>
        </x-slot:links>
    </x-marketing-legal>
</x-marketing-layout>

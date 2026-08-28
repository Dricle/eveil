<x-marketing-layout
    :title="config('app.name') . ' - Contact'"
    description="Reach the Eveil team for support, privacy and erasure requests, or self-hosted bugs and issues."
>
    <div style="max-width:1180px;margin:0 auto;padding:72px 24px 88px;display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start">
        <div>
            <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Contact</div>
            <h1 style="font-family:'Sora',sans-serif;font-weight:600;font-size:44px;line-height:1.1;letter-spacing:-.035em;margin:0 0 16px">Talk to a person, not a bot.</h1>
            <p style="margin:0 0 36px;color:rgba(232,236,242,.66);max-width:52ch">Placeholder. Set expectations here: who answers, in which languages, and how quickly. Technical questions about the self-hosted edition belong on GitHub, where the answer helps the next person too.</p>

            <div style="display:grid;gap:12px;margin-bottom:36px">
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:20px 22px">
                    <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.42);margin-bottom:8px">Support</div>
                    <a href="mailto:placeholder@eveil.cloud" style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em">placeholder@eveil.cloud</a>
                    <p style="margin:6px 0 0;font-size:14px;color:rgba(232,236,242,.55)">Placeholder response time.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:20px 22px">
                    <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.42);margin-bottom:8px">Privacy and erasure requests</div>
                    <a href="mailto:privacy@eveil.cloud" style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em">privacy@eveil.cloud</a>
                    <p style="margin:6px 0 0;font-size:14px;color:rgba(232,236,242,.55)">Placeholder statutory deadline.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:20px 22px">
                    <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.42);margin-bottom:8px">Bugs and self-hosting</div>
                    <a href="https://github.com/Dricle/eveil" style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em">github.com/Dricle/eveil</a>
                    <p style="margin:6px 0 0;font-size:14px;color:rgba(232,236,242,.55)">Issues and discussions, in the open.</p>
                </div>
            </div>

            <div style="border-top:1px solid rgba(232,236,242,.08);padding-top:22px;font-size:14px;color:rgba(232,236,242,.5)">
                <div style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.38);margin-bottom:8px">Registered entity</div>
                <p style="margin:0">Placeholder company name<br>Placeholder street address<br>Placeholder city, placeholder country<br>Placeholder company registration number</p>
            </div>
        </div>

        <div style="background:linear-gradient(180deg,rgba(232,236,242,.055),rgba(232,236,242,.02));border:1px solid rgba(232,236,242,.12);border-radius:14px;padding:28px">
            <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:24px;letter-spacing:-.025em;margin:0 0 6px">Send a message</h2>
            <p style="margin:0 0 24px;font-size:14px;color:rgba(232,236,242,.55)">Placeholder. This form is a mockup and does not submit anywhere yet.</p>
            <div style="display:grid;gap:16px">
                <div style="display:grid;gap:7px">
                    <label for="c-name" style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.5)">Name</label>
                    <input id="c-name" type="text" placeholder="Your name" class="field">
                </div>
                <div style="display:grid;gap:7px">
                    <label for="c-email" style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.5)">Email</label>
                    <input id="c-email" type="email" placeholder="you@company.com" class="field" style="font-family:'Geist Mono',monospace">
                </div>
                <div style="display:grid;gap:7px">
                    <label for="c-topic" style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.5)">Topic</label>
                    <select id="c-topic" class="field">
                        <option>Getting started</option>
                        <option>Billing and credits</option>
                        <option>Deliverability</option>
                        <option>Privacy or erasure request</option>
                        <option>Self-hosting</option>
                    </select>
                </div>
                <div style="display:grid;gap:7px">
                    <label for="c-msg" style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.5)">Message</label>
                    <textarea id="c-msg" rows="6" placeholder="What do you need?" class="field" style="resize:vertical"></textarea>
                </div>
                <button type="button" class="cta-btn" style="border:0;border-radius:9px;font-family:'Sora',sans-serif;font-weight:600;font-size:15px;padding:13px 22px;cursor:pointer">Send message</button>
                <p style="margin:0;font-size:13px;color:rgba(232,236,242,.45)">Placeholder. Note here what happens to what they type and how long it is kept.</p>
            </div>
        </div>
    </div>
</x-marketing-layout>

<x-marketing-layout
    :title="config('app.name') . ' - open-source outreach'"
    description="Give a URL and what you sell. Eveil finds the companies that need it and writes the outreach. Open source, self-hostable."
>
    <div style="border-bottom:1px solid rgba(232,236,242,.08);padding:10px 24px;display:flex;justify-content:center;gap:12px;align-items:center;font-size:13px;color:rgba(232,236,242,.72)">
        <span style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6fd3ec;border:1px solid rgba(111,211,236,.35);border-radius:999px;padding:2px 9px">Free trial</span>
        <span>5,000 credits at signup. Enough for one full campaign, through to replies. No card.</span>
    </div>

    <section id="top" style="border-bottom:1px solid rgba(232,236,242,.08)">
        <div style="max-width:1180px;margin:0 auto;padding:96px 24px 80px;text-align:center">
            <div style="display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(232,236,242,.14);background:rgba(232,236,242,.03);border-radius:999px;padding:6px 14px;font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:rgba(232,236,242,.7);margin-bottom:30px">
                <span style="width:6px;height:6px;border-radius:999px;background:#6fd3ec;display:block"></span>
                <span>Hosted Eveil. Open source, AGPL-3.0</span>
            </div>
            <h1 style="font-family:'Sora',sans-serif;font-weight:600;font-size:68px;line-height:1.04;letter-spacing:-.035em;margin:0 auto 22px;max-width:20ch;text-wrap:balance">A CMO you don't hire, running a team of agents that sells your product.</h1>
            <p style="font-size:18.5px;line-height:1.6;max-width:64ch;margin:0 auto 40px;color:rgba(232,236,242,.66);text-wrap:pretty">Paste your product URL. Eveil reads the site, works out who buys it, finds those companies and the people at them, writes the sequence, sends it from your own mailbox, and reads the replies. You approve as much or as little as you want.</p>

            <form action="{{ \Illuminate\Support\Facades\Route::has('register') ? route('register') : route('home') }}" method="get" style="max-width:600px;margin:0 auto;background:linear-gradient(180deg,rgba(232,236,242,.055),rgba(232,236,242,.02));border:1px solid rgba(232,236,242,.12);border-radius:14px;padding:20px;text-align:left">
                <label for="url" style="display:block;font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.5);margin-bottom:9px">Your product URL</label>
                <div style="display:flex;gap:9px">
                    <input id="url" name="url" type="url" required placeholder="https://yourproduct.com" class="field" style="flex:1;min-width:0;font-family:'Geist Mono',monospace">
                    <button type="submit" class="cta-btn" style="border:0;border-radius:9px;font-family:'Sora',sans-serif;font-weight:600;font-size:15px;padding:12px 22px;cursor:pointer;white-space:nowrap">Start free</button>
                </div>
                <p style="margin:13px 0 0;font-size:13.5px;color:rgba(232,236,242,.5)">5,000 credits, no card, no setup wizard. It shows you a product portrait to correct before it writes a word.</p>
            </form>

            <div style="display:flex;gap:28px;justify-content:center;margin-top:32px;font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:rgba(232,236,242,.42);flex-wrap:wrap">
                <span>Self-hostable</span>
                <span>Your own mailbox</span>
                <span>No tracking pixels</span>
            </div>

            <figure style="margin:56px 0 0;border:1px solid rgba(232,236,242,.12);border-radius:14px;overflow:hidden;background:#101520">
                <div style="display:flex;align-items:center;gap:7px;padding:11px 14px;border-bottom:1px solid rgba(232,236,242,.08)">
                    <span style="width:9px;height:9px;border-radius:999px;background:rgba(232,236,242,.18);display:block"></span>
                    <span style="width:9px;height:9px;border-radius:999px;background:rgba(232,236,242,.18);display:block"></span>
                    <span style="width:9px;height:9px;border-radius:999px;background:rgba(232,236,242,.18);display:block"></span>
                    <span style="font-family:'Geist Mono',monospace;font-size:11px;color:rgba(232,236,242,.4);margin-left:10px">discovery run / live</span>
                </div>
                <div style="aspect-ratio:16/8.4;display:flex;align-items:center;justify-content:center;background-image:repeating-linear-gradient(135deg, rgba(111,211,236,.07) 0 7px, transparent 7px 15px)">
                    <span style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#6fd3ec;border:1px solid rgba(111,211,236,.3);border-radius:8px;padding:8px 13px;background:#0b0e14">screenshot / discovery run</span>
                </div>
            </figure>
            <figcaption style="margin:12px 0 0;font-size:13px;color:rgba(232,236,242,.42)">Live progress on a running discovery job, with cancel and per-task replay.</figcaption>
        </div>
    </section>

    <section id="how" style="border-bottom:1px solid rgba(232,236,242,.08)">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:end;margin-bottom:44px">
                <div>
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">How it works</div>
                    <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0">Five stages between a URL and a qualified lead.</h2>
                </div>
                <p style="margin:0;color:rgba(232,236,242,.62);max-width:48ch">Every company gets a fit score and the sentence that justifies it. The pipeline runs over a bundled, self-hosted search engine, so no paid data API is required to start and no purchased list appears anywhere in it.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px">
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:22px 20px 26px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;color:#6fd3ec;margin-bottom:14px">01</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:17.5px;letter-spacing:-.02em;margin:0 0 8px">Search planning</h4>
                    <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">Target profiles become the actual queries that will find those companies.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:22px 20px 26px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;color:#6fd3ec;margin-bottom:14px">02</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:17.5px;letter-spacing:-.02em;margin:0 0 8px">Qualification</h4>
                    <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">Each company is read and scored for fit, with the reasoning attached.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:22px 20px 26px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;color:#6fd3ec;margin-bottom:14px">03</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:17.5px;letter-spacing:-.02em;margin:0 0 8px">Contact discovery</h4>
                    <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">The people who would actually answer, found on the company's own pages.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:22px 20px 26px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;color:#6fd3ec;margin-bottom:14px">04</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:17.5px;letter-spacing:-.02em;margin:0 0 8px">Pattern inference</h4>
                    <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">The company's email convention, inferred rather than guessed one address at a time.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:22px 20px 26px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;color:#6fd3ec;margin-bottom:14px">05</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:17.5px;letter-spacing:-.02em;margin:0 0 8px">Verification</h4>
                    <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">Addresses checked before a single mail is queued. Free, always.</p>
                </div>
            </div>
            <p style="margin:20px 0 0;font-size:13.5px;color:rgba(232,236,242,.42);max-width:74ch">A market that turns out to be forty companies is reported as a result, not padded with noise to fill a quota.</p>
        </div>
    </section>

    <section id="agents" style="border-bottom:1px solid rgba(232,236,242,.08);background:#0d1119">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:end;margin-bottom:44px">
                <div>
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">The team</div>
                    <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0">Nine agents, one knowledge base.</h2>
                </div>
                <p style="margin:0;color:rgba(232,236,242,.62);max-width:48ch">Every stage from discovery to sending reads the same understanding of your product, so nothing is re-explained and nothing contradicts itself.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Research</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Knowledge base</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Site analysis, plus an optional linked GitHub repo for a deeper technical read. Ask it questions. Set one writing style for the whole project: tone, language, banned words.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Strategy</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Target profiles</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Who buys the product and why they switch: derived, always editable. Customer profiles and partner profiles, for reach through whoever already touches the customer.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Prospecting</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Companies &amp; contacts</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Approval workflow, status per contact, search across everything discovered, and import when you already have a list.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Copy</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Campaigns &amp; sequences</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Generated sequences with reorderable steps and variants. Regenerate one missing step on its own, without rewriting the sequence around it.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Delivery</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Sending &amp; deliverability</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Plain SMTP from your own mailbox, with no relay and no shared domain. Daily caps, ramp-up, pacing spread across the day, never bursty.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Conversation</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Unified inbox</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Replies read back over IMAP and threaded on the mail's own Message-ID. The reply handler pauses, reschedules, asks for the right contact, or suppresses.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Infrastructure</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Mailbox management</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">SMTP and IMAP with presets for Infomaniak, OVH, Gandi, Zoho, Gmail and Microsoft 365. A connection test that names the exact cause of a refusal.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Team</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Organizations &amp; roles</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Three separate permission scopes, instance, organization and project, never merged into one role column. Invitations and multi-project grants in both editions.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:26px 24px 28px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Compliance</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Retention &amp; erasure</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Automatic purge on CNIL-referenced defaults. Erasure keeps a one-way hash only, enough to refuse re-contacting someone forever.</p>
                </div>
            </div>
        </div>
    </section>

    <section style="border-bottom:1px solid rgba(232,236,242,.08)">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px;display:grid;grid-template-columns:1fr 1.1fr;gap:64px;align-items:center">
            <div>
                <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Autonomy</div>
                <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0 0 18px">Three notches. The safety valves never move.</h2>
                <p style="margin:0 0 12px;color:rgba(232,236,242,.62)">Autonomy removes approval checkpoints, not guardrails. Circuit breakers apply at every notch, autonomous included: bounce rate, spam complaints, negative-reply rate, auth failures.</p>
                <p style="margin:0;color:rgba(232,236,242,.62)">Plus a bounce breaker scoped per mailbox and a three-layer suppression list: project, mailbox, instance.</p>
            </div>
            <div style="display:grid;gap:12px">
                <div style="border:1px solid rgba(232,236,242,.09);background:#101520;border-radius:12px;padding:20px 22px;display:grid;grid-template-columns:auto 1fr;gap:16px;align-items:baseline">
                    <span style="font-family:'Geist Mono',monospace;font-size:11px;color:rgba(232,236,242,.35)">01</span>
                    <div>
                        <div style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin-bottom:5px">Supervised</div>
                        <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">You approve every company, contact and mail before it moves.</p>
                    </div>
                </div>
                <div style="border:1px solid rgba(111,211,236,.4);background:linear-gradient(180deg,rgba(111,211,236,.09),rgba(111,211,236,.03));border-radius:12px;padding:20px 22px;display:grid;grid-template-columns:auto 1fr;gap:16px;align-items:baseline">
                    <span style="font-family:'Geist Mono',monospace;font-size:11px;color:#6fd3ec">02</span>
                    <div>
                        <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:5px">
                            <span style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em">Semi-auto</span>
                            <span style="font-family:'Geist Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:#6fd3ec">default</span>
                        </div>
                        <p style="margin:0;font-size:14px;color:rgba(232,236,242,.66)">Research and writing run on their own. Sending is the one thing that waits for you.</p>
                    </div>
                </div>
                <div style="border:1px solid rgba(232,236,242,.09);background:#101520;border-radius:12px;padding:20px 22px;display:grid;grid-template-columns:auto 1fr;gap:16px;align-items:baseline">
                    <span style="font-family:'Geist Mono',monospace;font-size:11px;color:rgba(232,236,242,.35)">03</span>
                    <div>
                        <div style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin-bottom:5px">Autonomous</div>
                        <p style="margin:0;font-size:14px;color:rgba(232,236,242,.58)">End to end without you. Breakers stay armed and a tripped one stops the send.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="editions" style="border-bottom:1px solid rgba(232,236,242,.08);background:#0d1119">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:end;margin-bottom:40px">
                <div>
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Editions</div>
                    <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0">Same code. No feature gate.</h2>
                </div>
                <p style="margin:0;color:rgba(232,236,242,.62);max-width:48ch">Nothing is withheld from self-hosted to make cloud look better. Cloud sells convenience and a head start.</p>
            </div>
            <div style="border:1px solid rgba(232,236,242,.1);border-radius:14px;overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:14.5px;text-align:left">
                    <thead>
                        <tr style="background:rgba(232,236,242,.04)">
                            <th style="padding:14px 18px;font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500;width:20%">What matters</th>
                            <th style="padding:14px 18px;font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);font-weight:500">Self-hosted</th>
                            <th style="padding:14px 18px;font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6fd3ec;font-weight:500">Cloud</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);font-weight:500">Cost</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Free, forever. AGPL-3.0.</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.75);background:rgba(111,211,236,.045)">Pay-as-you-go credits, no subscription.</td>
                        </tr>
                        <tr>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);font-weight:500">AI provider</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Bring your own key: Anthropic, OpenAI, whichever you already pay for.</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.75);background:rgba(111,211,236,.045)">Included, metered in credits. Never a token count, never a model name.</td>
                        </tr>
                        <tr>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);font-weight:500">Your data</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Never leaves your machine.</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.75);background:rgba(111,211,236,.045)">Hosted, managed, backed up for you.</td>
                        </tr>
                        <tr>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);font-weight:500">Mailboxes, seats, multi-user</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Unlimited. Organizations, roles and invitations included.</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.75);background:rgba(111,211,236,.045)">Identical. Same code, same limits.</td>
                        </tr>
                        <tr>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);font-weight:500">Cold start</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Its own host registry and page cache, built from its own runs only.</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.75);background:rgba(111,211,236,.045)">Born smart. One shared registry and cache fed by every customer, so a new project skips work someone else's run already paid for.</td>
                        </tr>
                        <tr>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);font-weight:500">Setup</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.62)">Docker, five minutes, your own reverse proxy for TLS.</td>
                            <td style="padding:15px 18px;border-top:1px solid rgba(232,236,242,.08);color:rgba(232,236,242,.75);background:rgba(111,211,236,.045)">An account. Nothing to run.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap">
                <a href="#top" class="cta-btn" style="padding:11px 22px;border-radius:9px;font-family:'Sora',sans-serif;font-weight:600;font-size:15px">Start on cloud</a>
                <a href="https://github.com/Dricle/eveil" class="ghost-btn" style="border:1px solid rgba(232,236,242,.18);padding:11px 22px;border-radius:9px;font-family:'Sora',sans-serif;font-weight:600;font-size:15px">Self-host it</a>
            </div>
        </div>
    </section>

    <section id="pricing" style="border-bottom:1px solid rgba(232,236,242,.08)">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:end;margin-bottom:44px">
                <div>
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Pricing</div>
                    <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0">Credits. No plans, no tiers, no recurring invoice.</h2>
                </div>
                <p style="margin:0;color:rgba(232,236,242,.62);max-width:48ch">Top up whatever you choose at one flat published rate. Auto top-up on a threshold, Stripe's own hosted portal for invoices and cards.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1;letter-spacing:-.03em;margin-bottom:12px">€0.10</div>
                    <div style="font-size:13.5px;color:rgba(232,236,242,.58)">per qualified lead, all in: found, read, written to and delivered.</div>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1;letter-spacing:-.03em;margin-bottom:12px">3,500</div>
                    <div style="font-size:13.5px;color:rgba(232,236,242,.58)">credits for a full 100-lead campaign, end to end. Roughly one exported contact on Apollo.</div>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1;letter-spacing:-.03em;margin-bottom:12px">5,000</div>
                    <div style="font-size:13.5px;color:rgba(232,236,242,.58)">credits free at signup, enough for one full campaign through to replies.</div>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1;letter-spacing:-.03em;margin-bottom:12px">€0</div>
                    <div style="font-size:13.5px;color:rgba(232,236,242,.58)">for verification, SMTP sends and IMAP reads. Always. Credits never expire.</div>
                </div>
            </div>
        </div>
    </section>

    <section style="border-bottom:1px solid rgba(232,236,242,.08);background:#0d1119">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:end;margin-bottom:40px">
                <div>
                    <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">What we don't do</div>
                    <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0">Five things missing on purpose.</h2>
                </div>
                <p style="margin:0;color:rgba(232,236,242,.62);max-width:48ch">These are product decisions, not gaps. The only signal worth tracking is a positive reply.</p>
            </div>
            <div style="display:grid;gap:0;border-top:1px solid rgba(232,236,242,.08)">
                <div style="padding:20px 0;border-bottom:1px solid rgba(232,236,242,.08);display:grid;grid-template-columns:270px 1fr;gap:24px;align-items:baseline">
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin:0">No open or click tracking</h4>
                    <p style="margin:0;color:rgba(232,236,242,.58);font-size:14.5px">No pixel, no link rewriting, no open-rate metric anywhere in the product.</p>
                </div>
                <div style="padding:20px 0;border-bottom:1px solid rgba(232,236,242,.08);display:grid;grid-template-columns:270px 1fr;gap:24px;align-items:baseline">
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin:0">No mailbox warm-up</h4>
                    <p style="margin:0;color:rgba(232,236,242,.58);font-size:14.5px">A real, already-warm mailbox sending at a human pace, not a fresh domain ramped by a bot network.</p>
                </div>
                <div style="padding:20px 0;border-bottom:1px solid rgba(232,236,242,.08);display:grid;grid-template-columns:270px 1fr;gap:24px;align-items:baseline">
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin:0">No OAuth</h4>
                    <p style="margin:0;color:rgba(232,236,242,.58);font-size:14.5px">SMTP and IMAP credentials only, in both editions.</p>
                </div>
                <div style="padding:20px 0;border-bottom:1px solid rgba(232,236,242,.08);display:grid;grid-template-columns:270px 1fr;gap:24px;align-items:baseline">
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin:0">No unsubscribe link</h4>
                    <p style="margin:0;color:rgba(232,236,242,.58);font-size:14.5px">Opt-out is a sentence a person could have typed: reply STOP. Never a compliance footer.</p>
                </div>
                <div style="padding:20px 0;border-bottom:1px solid rgba(232,236,242,.08);display:grid;grid-template-columns:270px 1fr;gap:24px;align-items:baseline">
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.02em;margin:0">No purchased database</h4>
                    <p style="margin:0;color:rgba(232,236,242,.58);font-size:14.5px">Every lead shown was found and read live, not pulled from a stale list.</p>
                </div>
            </div>
        </div>
    </section>

    <section style="border-bottom:1px solid rgba(232,236,242,.08)">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px">
            <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Who it's for</div>
            <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0 0 40px">Three people, one codebase.</h2>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Self-hosted</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Technical solo founder</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Operational in fifteen minutes, with no third-party API key to sign up for beyond the AI provider you already pay for.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6fd3ec;margin-bottom:12px">Cloud</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Small growth team</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Multi-user and managed hosting, with billing you can predict: a balance you top up, not an invoice that surprises you.</p>
                </div>
                <div style="background:#101520;border:1px solid rgba(232,236,242,.09);border-radius:12px;padding:28px 24px">
                    <div style="font-family:'Geist Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(232,236,242,.45);margin-bottom:12px">Self-hosted</div>
                    <h4 style="font-family:'Sora',sans-serif;font-weight:600;font-size:20px;letter-spacing:-.02em;margin:0 0 10px">Instance superadmin</h4>
                    <p style="margin:0;font-size:14.5px;color:rgba(232,236,242,.6)">Configure the AI provider, choose a model per agent and close registration from a screen, not by editing files.</p>
                </div>
            </div>
        </div>
    </section>

    <section style="border-bottom:1px solid rgba(232,236,242,.08);background:#0d1119">
        <div style="max-width:1180px;margin:0 auto;padding:80px 24px;display:grid;grid-template-columns:.8fr 1.2fr;gap:60px">
            <div>
                <div style="font-family:'Geist Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:#6fd3ec;margin-bottom:14px">Questions</div>
                <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:40px;line-height:1.12;letter-spacing:-.03em;margin:0">Before you paste a URL.</h2>
            </div>
            <div style="border-top:1px solid rgba(232,236,242,.08)">
                <details style="border-bottom:1px solid rgba(232,236,242,.08);padding:20px 0">
                    <summary style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.02em;display:flex;justify-content:space-between;gap:16px"><span>Whose mailbox does it send from?</span><span style="color:#6fd3ec">+</span></summary>
                    <p style="margin:12px 0 0;color:rgba(232,236,242,.6);max-width:62ch">Yours. Plain SMTP, no relay and no shared sending domain, so your replies land where you already read mail. Presets cover Infomaniak, OVH, Gandi, Zoho, Gmail and Microsoft 365.</p>
                </details>
                <details style="border-bottom:1px solid rgba(232,236,242,.08);padding:20px 0">
                    <summary style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.02em;display:flex;justify-content:space-between;gap:16px"><span>Where do the leads come from?</span><span style="color:#6fd3ec">+</span></summary>
                    <p style="margin:12px 0 0;color:rgba(232,236,242,.6);max-width:62ch">A bundled, self-hosted search engine and the companies' own pages, read live at qualification time. No purchased database, and no paid data API required to start.</p>
                </details>
                <details style="border-bottom:1px solid rgba(232,236,242,.08);padding:20px 0">
                    <summary style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.02em;display:flex;justify-content:space-between;gap:16px"><span>Can I see and change what the AI decided?</span><span style="color:#6fd3ec">+</span></summary>
                    <p style="margin:12px 0 0;color:rgba(232,236,242,.6);max-width:62ch">At every step. The product portrait comes back for correction before anything is written, target profiles are editable, sequences are reorderable, and hand-edits are never overwritten by a re-derivation.</p>
                </details>
                <details style="border-bottom:1px solid rgba(232,236,242,.08);padding:20px 0">
                    <summary style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.02em;display:flex;justify-content:space-between;gap:16px"><span>What language does it write in?</span><span style="color:#6fd3ec">+</span></summary>
                    <p style="margin:12px 0 0;color:rgba(232,236,242,.6);max-width:62ch">The prospect's. Language is detected per lead's own market rather than fixed per project, so a mail follows the person receiving it.</p>
                </details>
                <details style="border-bottom:1px solid rgba(232,236,242,.08);padding:20px 0">
                    <summary style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.02em;display:flex;justify-content:space-between;gap:16px"><span>Will I get a surprise AI bill?</span><span style="color:#6fd3ec">+</span></summary>
                    <p style="margin:12px 0 0;color:rgba(232,236,242,.6);max-width:62ch">There is no bill, only a balance. Credits are prepaid at one flat rate, never expire, and auto top-up only fires at the threshold you set. Invoices and cards live in Stripe's hosted portal.</p>
                </details>
                <details style="border-bottom:1px solid rgba(232,236,242,.08);padding:20px 0">
                    <summary style="font-family:'Sora',sans-serif;font-weight:600;font-size:18px;letter-spacing:-.02em;display:flex;justify-content:space-between;gap:16px"><span>Can I move to self-hosted later?</span><span style="color:#6fd3ec">+</span></summary>
                    <p style="margin:12px 0 0;color:rgba(232,236,242,.6);max-width:62ch">It's the same AGPL-3.0 codebase: Docker, five minutes, your own reverse proxy for TLS. Bring your own AI key and nothing is missing.</p>
                </details>
            </div>
        </div>
    </section>

    <section>
        <div style="max-width:1180px;margin:0 auto;padding:96px 24px;text-align:center">
            <h2 style="font-family:'Sora',sans-serif;font-weight:600;font-size:52px;line-height:1.06;letter-spacing:-.035em;margin:0 auto 20px;max-width:22ch">Give it a URL. Read the replies.</h2>
            <p style="margin:0 auto 32px;max-width:54ch;color:rgba(232,236,242,.62);font-size:17px">5,000 credits at signup, capped to one project and to leads actually discovered. Enough for a full campaign through to replies.</p>
            <form action="{{ \Illuminate\Support\Facades\Route::has('register') ? route('register') : route('home') }}" method="get" style="display:flex;gap:9px;justify-content:center;max-width:520px;margin:0 auto">
                <input type="url" name="url" required placeholder="https://yourproduct.com" class="field" style="flex:1;min-width:0;font-family:'Geist Mono',monospace;background:#101520;padding:13px 14px">
                <button type="submit" class="cta-btn" style="border:0;border-radius:9px;font-family:'Sora',sans-serif;font-weight:600;font-size:15px;padding:13px 24px;cursor:pointer">Start free</button>
            </form>
        </div>
    </section>
</x-marketing-layout>

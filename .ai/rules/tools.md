---
paths:
  - 'app/Ai/Tools/**'
---

# Tools

## Reply tools are thin; the machinery is ReplyOutcomes
Each tool in `app/Ai/Tools/` is a description + schema + one call into `App\Services\Outreach\ReplyOutcomes`. Keep it that way: everything compliance-critical (suppression, an auto-reply RESUMING rather than pausing, the org-wide escalation on a second opt-out) must be provable in a test without asking a provider to answer the same way twice.

Tools receive the row they act on via the constructor (`new SuppressLead($reply)`), so the model chooses WHAT happens and never which record it happens to. `ReplyHandler::tools()` is the whole decision surface; there is deliberately no tool that sends a reply. An agent answering a real question would end the "reads like a person typed it" promise in one message.

`#[MaxSteps(3)]` on the agent: it calls one tool and stops.

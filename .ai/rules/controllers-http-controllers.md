---
paths:
  - 'app/Http/Controllers/AiInstructionsController.php,app/Http/Controllers/EmailInstructionsController.php,app/Http/Controllers/SocialInstructionsController.php,app/Http/Controllers/ProjectController.php'
---

# Controllers Http Controllers

## Every writing-tone box lives on the AI instructions settings screen, saved separately
"How Emails are written" (`prompt_instructions`) and one "How {network} posts are written" box per `SocialPlatform` (`{platform}_prompt_instructions`) are shown together on `settings/AiInstructions.vue` (`AiInstructionsController::edit()`, project-scope nav item), but each saves through its own route (`EmailInstructionsController`, `SocialInstructionsController` with `{platform}`) rather than a shared form — a different agent or network reads each one (`EveilAgent::emailWritingInstructions()` / `postInstructions()`), so they never submit together. `prompt_instructions` is NOT in `ProjectRequest` any more — do not re-add it there. No tone box lives on a posts queue page (those keep only the posting cadence). See `.ai/rules/ai.md` and `.ai/rules/agents-models.md` for the agent side.

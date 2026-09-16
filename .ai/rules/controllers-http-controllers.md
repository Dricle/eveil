---
paths:
  - 'app/Http/Controllers/AiInstructionsController.php,app/Http/Controllers/EmailInstructionsController.php,app/Http/Controllers/LinkedinInstructionsController.php,app/Http/Controllers/ProjectController.php'
---

# Controllers Http Controllers

## Both writing-tone boxes live on the AI instructions settings screen, saved separately
"How Emails are written" (`prompt_instructions`) and "How LinkedIn posts are written" (`linkedin_prompt_instructions`) are shown together on `settings/AiInstructions.vue` (`AiInstructionsController::edit()`, project-scope nav item), but each saves through its own small controller/request (`EmailInstructionsController`, `LinkedinInstructionsController`) rather than a shared form — a different agent reads each one (`EveilAgent::emailWritingInstructions()` / `linkedinInstructions()`), so they never submit together. `prompt_instructions` is NOT in `ProjectRequest` any more (was, until it moved off `settings/Project.vue`) — do not re-add it there. `linkedin_prompt_instructions` does not live on the LinkedIn posts queue page either (that page keeps only the posting cadence). See `.ai/rules/ai.md` and `.ai/rules/agents-models.md` for the agent side.

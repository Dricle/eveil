import type { Insight, Project } from '@/types'

/**
 * A feature insight, handed to the user's coding agent. `standalone` carries
 * everything Eveil knows so any agent can judge it; `mcp` only names the item
 * and lets an agent connected to Eveil's MCP server fetch it and close it.
 */
export function featurePrompt (insight: Insight, project: Project, mode: 'standalone' | 'mcp'): string {
    const judge = `Before writing any code, check whether the codebase already does this, then judge whether it is worth building for ${project.name}: does it fit what the product is for, how many users would it serve, and how much work is it. If it is not worth it, say why and stop.`

    if (mode === 'mcp') {
        return [
            `Use the Eveil MCP server (${window.location.origin}/mcp) to read the feature request #${insight.id} of the project "${project.slug}": call list-insights with project "${project.slug}" and insight_id ${insight.id}. Read the thread it links to if there is one.`,
            judge,
            'If it is worth it, plan it, build it with tests, and tell me what you changed.',
            `Then close it in Eveil with update-insight (project "${project.slug}", insight_id ${insight.id}): status "used" once built, or "dismissed" if you decided it is not worth building.`
        ].join('\n\n')
    }

    const source = insight.source === 'reddit'
        ? `People asked for it on Reddit${insight.details.subreddit ? ` (r/${insight.details.subreddit})` : ''}.`
        : 'A competitor offers it and this product does not.'

    return [
        `I work on ${project.name} (${project.url}). Eveil, the tool that watches what people say about it, noted a feature request. ${source}`,
        `Feature: ${insight.title}`,
        `Why: ${insight.angle}`,
        insight.url ? `Source: ${insight.url}` : null,
        insight.details.impact || insight.details.effort ? `Eveil's guess: ${insight.details.impact ?? 'unknown'} impact, ${insight.details.effort ?? 'unknown'} effort.` : null,
        judge,
        'If it is worth it, plan it, build it with tests, and tell me what you changed.'
    ].filter(Boolean).join('\n\n')
}

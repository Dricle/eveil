import type { Insight, Project } from '@/types'

/**
 * A missing feature or an acquisition idea, handed to the user's coding
 * agent. `standalone` carries everything Eveil knows so any agent can judge
 * it; `mcp` only names the item and lets an agent connected to Eveil's MCP
 * server fetch it and close it.
 */
export function insightPrompt (insight: Insight, project: Project, mode: 'standalone' | 'mcp'): string {
    const feature = insight.kind === 'feature'
    const what = feature ? 'feature request' : 'acquisition idea (a way to bring more people to the site)'
    const judge = feature
        ? `Before writing any code, check whether the codebase already does this, then judge whether it is worth building for ${project.name}: does it fit what the product is for, how many users would it serve, and how much work is it. If it is not worth it, say why and stop.`
        : `Before writing any code, check whether the site already does this, then judge whether it is worth doing for ${project.name}: would it bring the right visitors, and how much work is it. If it is not worth it, say why and stop. Some of it may need me rather than code (an account to open, a partner to contact): say which part.`

    if (mode === 'mcp') {
        return [
            `Use the Eveil MCP server (${window.location.origin}/mcp) to read the ${what} #${insight.id} of the project "${project.slug}": call list-insights with project "${project.slug}" and insight_id ${insight.id}. Read the page it links to if there is one.`,
            judge,
            'If it is worth it, plan it, build it with tests, and tell me what you changed.',
            `Then close it in Eveil with update-insight (project "${project.slug}", insight_id ${insight.id}): status "used" once done, or "dismissed" if you decided it is not worth it.`
        ].join('\n\n')
    }

    const threads = insight.details.threads ?? []
    const source = !feature
        ? 'It noticed the site does not use it yet.'
        : insight.source === 'reddit'
            ? threads.length > 1 ? `${threads.length} Reddit threads asked for it.` : `People asked for it on Reddit${insight.details.subreddit ? ` (r/${insight.details.subreddit})` : ''}.`
            : `A competitor offers it and this product does not.${threads.length ? ` ${threads.length} Reddit ${threads.length > 1 ? 'threads' : 'thread'} asked for it too.` : ''}`

    return [
        `I work on ${project.name} (${project.url}). Eveil, the tool that does its marketing, noted a ${what}. ${source}`,
        `${feature ? 'Feature' : 'Idea'}: ${insight.title}`,
        `Why: ${insight.angle}`,
        threads.length
            ? `Threads asking:\n${threads.map(thread => `- ${thread.title} (${thread.url})${thread.evidence ? `\n  ${thread.evidence}` : ''}`).join('\n')}`
            : insight.url ? `Source: ${insight.url}` : null,
        insight.details.impact || insight.details.effort ? `Eveil's guess: ${insight.details.impact ?? 'unknown'} impact, ${insight.details.effort ?? 'unknown'} effort.` : null,
        judge,
        'If it is worth it, plan it, build it with tests, and tell me what you changed.'
    ].filter(Boolean).join('\n\n')
}

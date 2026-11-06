/** Shape returned by App\Support\AiStats::summary(). */
export type AiStats = {
    spendUsd: number;
    tokens: { input: number; output: number; cached: number };
    runs: { total: number; succeeded: number; byAgent: Record<string, number> };
    pullRequests: { opened: number; merged: number; autoMerged: number };
    avgIssueToPrMinutes: number | null;
    recentRuns: {
        agent: string;
        title: string;
        status: string;
        costUsd: number;
        finishedAt: string;
    }[];
    updatedAt: string | null;
};

/** One agent on the AI Workflow page, read from agents/<key>/agent.md by AiWorkflowController. */
export type AgentSummary = {
    key: string;
    name: string;
    description: string;
    model: string;
    effort: string;
    budgetUsd: number;
    usesWeb: boolean;
    path: string;
    prompt: string;
};

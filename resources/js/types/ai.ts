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

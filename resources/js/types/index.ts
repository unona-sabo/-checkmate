export * from './auth';
export * from './checkmate';
export * from './navigation';
export * from './ui';

import type { Auth } from './auth';
import type { Workspace } from './checkmate';

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    currentWorkspace: (Workspace & { role: string }) | null;
    workspaces: Pick<Workspace, 'id' | 'name' | 'slug'>[];
    [key: string]: unknown;
};

import { usePage } from '@inertiajs/react';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name?.split(' ')[0] ?? '';
    const title = breadcrumbs[breadcrumbs.length - 1]?.title ?? 'Dashboard';

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-sidebar-border/50 bg-white pl-14 pr-6 lg:px-6">
            <h1 className="text-lg font-semibold tracking-tight text-neutral-900">
                {title}
            </h1>

            {auth.user && (
                <div className="flex items-center gap-3 text-sm text-neutral-600">
                    <span className="hidden sm:inline">
                        Welcome back,
                        <span className="font-medium text-neutral-900">
                            {' '}
                            {firstName}
                        </span>
                    </span>
                </div>
            )}
        </header>
    );
}

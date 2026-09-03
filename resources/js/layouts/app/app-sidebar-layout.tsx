import { Menu } from 'lucide-react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import type { AppLayoutProps } from '@/types';

function FloatingSidebarTrigger() {
    const { isMobile, state, openMobile, setOpen, setOpenMobile } =
        useSidebar();

    const showFloating = isMobile ? !openMobile : state === 'collapsed';

    if (!showFloating) {
        return null;
    }

    return (
        <Button
            variant="ghost"
            size="icon"
            aria-label="Open sidebar"
            className="fixed top-3 left-3 z-40 h-10 w-10 rounded-xl border border-sidebar-border bg-white text-sidebar-foreground shadow-sm transition-all duration-200 ease-in-out hover:scale-105 hover:bg-sidebar-accent"
            onClick={() => (isMobile ? setOpenMobile(true) : setOpen(true))}
        >
            <Menu className="size-5" />
        </Button>
    );
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <FloatingSidebarTrigger />
                {children}
            </AppContent>
        </AppShell>
    );
}

import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-3 grid flex-1 text-left group-data-[collapsible=icon]:hidden">
                <span className="whitespace-nowrap text-[15px] leading-tight font-semibold text-sidebar-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}

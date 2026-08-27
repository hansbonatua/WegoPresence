import { Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types';

type PaginationProps = React.ComponentProps<'nav'> & {
    links: PaginationLink[];
};

function PaginationItem({
    link,
    isCurrent = false,
}: {
    link: PaginationLink;
    isCurrent?: boolean;
}) {
    if (link.url === null) {
        return (
            <span
                aria-disabled="true"
                className="pointer-events-none inline-flex h-8 w-8 items-center justify-center rounded-md text-sm text-muted-foreground/40"
            >
                {link.label.includes('Previous') && (
                    <ChevronLeftIcon className="size-4" />
                )}
                {link.label.includes('Next') && (
                    <ChevronRightIcon className="size-4" />
                )}
            </span>
        );
    }

    return (
        <Link
            href={link.url}
            preserveScroll
            preserveState
            aria-current={isCurrent ? 'page' : undefined}
            className={cn(
                'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition-colors',
                isCurrent
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
        >
            {link.label.includes('Previous') && (
                <ChevronLeftIcon className="size-4" />
            )}
            {link.label.includes('Next') && <ChevronRightIcon className="size-4" />}
            {!link.label.includes('Previous') && !link.label.includes('Next') && (
                <span
                    className={cn(
                        !isCurrent && 'hidden sm:inline',
                        isCurrent && 'inline',
                    )}
                >
                    {link.label}
                </span>
            )}
        </Link>
    );
}

export function Pagination({ links, className, ...props }: PaginationProps) {
    if (!links.length) {
        return null;
    }

    const pageNumbers = links.filter(
        (link) => !link.label.includes('Previous') && !link.label.includes('Next'),
    );
    const lastPage = Number(pageNumbers[pageNumbers.length - 1]?.label ?? 1);
    const currentPage = pageNumbers.find((link) => link.active)?.label ?? '1';

    return (
        <nav
            role="navigation"
            aria-label="Pagination"
            className={cn('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}
            {...props}
        >
            <p className="text-sm text-muted-foreground">
                Page {currentPage} of {lastPage}
            </p>

            <div className="flex items-center gap-1">
                {links.map((link, index) => (
                    <PaginationItem
                        key={`${link.label}-${index}`}
                        link={link}
                        isCurrent={link.active}
                    />
                ))}
            </div>
        </nav>
    );
}

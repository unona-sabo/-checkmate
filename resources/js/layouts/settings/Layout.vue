<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { show as showBackup } from '@/routes/backup';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type AppPageProps, type NavItem } from '@/types';

// Backup/restore is being reworked into a workspace-scoped export, so the
// tab is hidden for everyone except this account in the meantime.
const BACKUP_ALLOWED_EMAIL = 'ysabiekiia@air.io';

const page = usePage<AppPageProps>();

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Profile',
            href: editProfile(),
        },
        {
            title: 'Password',
            href: editPassword(),
        },
        {
            title: 'Two-Factor Auth',
            href: show(),
        },
        {
            title: 'Appearance',
            href: editAppearance(),
        },
    ];

    if (page.props.auth.user.email === BACKUP_ALLOWED_EMAIL) {
        items.push({
            title: 'Backup',
            href: showBackup(),
        });
    }

    items.push(
        {
            title: 'Achievements',
            href: '/settings/achievements',
        },
        {
            title: 'Project Updates',
            href: '/settings/project-updates',
        },
    );

    return items;
});

const { isCurrentUrl, isCurrentUrlPrefix } = useCurrentUrl();

const props = withDefaults(defineProps<{ wide?: boolean }>(), {
    wide: false,
});
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            {
                                'bg-muted':
                                    item.title === 'Project Updates'
                                        ? isCurrentUrlPrefix(item.href)
                                        : isCurrentUrl(item.href),
                            },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div :class="['flex-1', props.wide ? '' : 'md:max-w-2xl']">
                <section
                    :class="[props.wide ? 'w-full' : 'max-w-xl', 'space-y-12']"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>

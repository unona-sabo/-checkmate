<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    FolderOpen,
    Component,
    ClipboardList,
    Layers,
    Play,
    Bug,
    Palette,
    Drama,
    Rocket,
    BarChart3,
    Database,
    FileText,
    StickyNote,
    Sparkles,
    Activity,
    Calculator,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home } from '@/routes';
import { type AppPageProps, type NavItem, type Project } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage<AppPageProps>();
const { isCurrentUrlPrefix } = useCurrentUrl();

const currentProject = computed(() => {
    const props = page.props as Record<string, unknown>;
    return props.project as Project | undefined;
});

const hiddenSidebarCategories = computed(
    () => page.props.currentWorkspace?.hidden_sidebar_categories ?? [],
);

const mainNavItems: NavItem[] = [
    {
        title: 'Home',
        href: home(),
        icon: LayoutGrid,
    },
    {
        title: 'Projects',
        href: '/projects',
        icon: Component,
    },
];

const projectSubItems = computed(() => {
    if (!currentProject.value) return [];
    const projectId = currentProject.value.id;
    const hidden = hiddenSidebarCategories.value;

    return [
        {
            key: 'checklists',
            title: 'Checklists',
            href: `/projects/${projectId}/checklists`,
            icon: ClipboardList,
        },
        {
            key: 'test-suites',
            title: 'Test Suites',
            href: `/projects/${projectId}/test-suites`,
            icon: Layers,
        },
        {
            key: 'test-runs',
            title: 'Test Runs',
            href: `/projects/${projectId}/test-runs`,
            icon: Play,
        },
        {
            key: 'bugreports',
            title: 'Bugreports',
            href: `/projects/${projectId}/bugreports`,
            icon: Bug,
        },
        {
            key: 'design',
            title: 'Design',
            href: `/projects/${projectId}/design`,
            icon: Palette,
        },
        {
            key: 'automation',
            title: 'Automation',
            href: `/projects/${projectId}/automation`,
            icon: Drama,
        },
        {
            key: 'releases',
            title: 'Releases',
            href: `/projects/${projectId}/releases`,
            icon: Rocket,
        },
        {
            key: 'test-coverage',
            title: 'Test Coverage',
            href: `/projects/${projectId}/test-coverage`,
            icon: BarChart3,
        },
        {
            key: 'ai-generator',
            title: 'AI Generator',
            href: `/projects/${projectId}/ai-generator`,
            icon: Sparkles,
        },
        {
            key: 'test-data',
            title: 'Test Data',
            href: `/projects/${projectId}/test-data`,
            icon: Database,
        },
        {
            key: 'payout-monitor',
            title: 'Payout Monitor',
            href: `/projects/${projectId}/payout-monitor`,
            icon: Activity,
        },
        {
            key: 'balance-calculator',
            title: 'Balance Calculator',
            href: `/projects/${projectId}/balance-calculator`,
            icon: Calculator,
        },
        {
            key: 'documentations',
            title: 'Documentations',
            href: `/projects/${projectId}/documentations`,
            icon: FileText,
        },
        {
            key: 'notes',
            title: 'Notes',
            href: `/projects/${projectId}/notes`,
            icon: StickyNote,
        },
    ].filter((item) => !hidden.includes(item.key));
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <Link
                :href="home()"
                class="flex w-full items-center justify-center px-0 py-4"
            >
                <AppLogo />
            </Link>
            <WorkspaceSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <!-- Current Project Navigation -->
            <SidebarGroup v-if="currentProject" class="px-2 py-0">
                <SidebarGroupLabel class="text-sidebar-foreground/70"
                    >Current Project</SidebarGroupLabel
                >
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="
                                isCurrentUrlPrefix(
                                    `/projects/${currentProject.id}`,
                                )
                            "
                            :tooltip="currentProject.name"
                        >
                            <Link
                                :href="`/projects/${currentProject.id}`"
                                class="font-semibold"
                            >
                                <FolderOpen class="h-4 w-4" />
                                <span class="truncate">{{
                                    currentProject.name
                                }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem
                        v-for="item in projectSubItems"
                        :key="item.href"
                    >
                        <SidebarMenuButton
                            as-child
                            size="sm"
                            :is-active="isCurrentUrlPrefix(item.href)"
                            :tooltip="item.title"
                        >
                            <Link
                                :href="item.href"
                                class="pl-4 group-data-[collapsible=icon]:pl-0"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

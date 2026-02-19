<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, FolderOpen, Component, ClipboardList, Layers, Play, Bug, Palette, Drama, Rocket, BarChart3, Database, FileText, StickyNote, Sparkles } from 'lucide-vue-next';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
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
import { home } from '@/routes';
import { type NavItem, type Project } from '@/types';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const page = usePage();
const { isCurrentUrl, isCurrentUrlPrefix } = useCurrentUrl();

const currentProject = computed(() => {
    const props = page.props as Record<string, unknown>;
    return props.project as Project | undefined;
});

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
    return [
        { title: 'Checklists', href: `/projects/${projectId}/checklists`, icon: ClipboardList },
        { title: 'Test Suites', href: `/projects/${projectId}/test-suites`, icon: Layers },
        { title: 'Test Runs', href: `/projects/${projectId}/test-runs`, icon: Play },
        { title: 'Bugreports', href: `/projects/${projectId}/bugreports`, icon: Bug },
        { title: 'Design', href: `/projects/${projectId}/design`, icon: Palette },
        { title: 'Automation', href: `/projects/${projectId}/automation`, icon: Drama },
        { title: 'Releases', href: `/projects/${projectId}/releases`, icon: Rocket },
        { title: 'Test Coverage', href: `/projects/${projectId}/test-coverage`, icon: BarChart3 },
        { title: 'AI Generator', href: `/projects/${projectId}/ai-generator`, icon: Sparkles },
        { title: 'Test Data', href: `/projects/${projectId}/test-data`, icon: Database },
        { title: 'Documentations', href: `/projects/${projectId}/documentations`, icon: FileText },
        { title: 'Notes', href: `/projects/${projectId}/notes`, icon: StickyNote },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <Link :href="home()" class="flex w-full items-center justify-center px-0 py-4">
                <AppLogo />
            </Link>
            <WorkspaceSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <!-- Current Project Navigation -->
            <SidebarGroup v-if="currentProject" class="px-2 py-0">
                <SidebarGroupLabel class="text-sidebar-foreground/70">Current Project</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrlPrefix(`/projects/${currentProject.id}`)"
                            :tooltip="currentProject.name"
                        >
                            <Link :href="`/projects/${currentProject.id}`" class="font-semibold">
                                <FolderOpen class="h-4 w-4" />
                                <span class="truncate">{{ currentProject.name }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem v-for="item in projectSubItems" :key="item.href">
                        <SidebarMenuButton
                            as-child
                            size="sm"
                            :is-active="isCurrentUrlPrefix(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href" class="group-data-[collapsible=icon]:pl-0 pl-4">
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

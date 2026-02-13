<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, FolderOpen, Component, ClipboardList, Layers, Play, ChevronRight, Bug, FileText, StickyNote } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
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
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import { type NavItem, type Project } from '@/types';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

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

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
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
                            :is-active="isCurrentUrl(`/projects/${currentProject.id}`)"
                        >
                            <Link :href="`/projects/${currentProject.id}`" class="font-semibold">
                                <FolderOpen class="h-4 w-4" />
                                <span class="truncate">{{ currentProject.name }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem v-for="item in projectSubItems" :key="item.href">
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(item.href)"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" class="h-4 w-4" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

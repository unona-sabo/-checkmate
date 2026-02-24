<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { ArrowUp } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const show = ref(false);
const SCROLL_THRESHOLD = 300;
let hideTimer: ReturnType<typeof setTimeout> | null = null;
let activeTarget: Element | Window | null = null;

const clearHideTimer = () => {
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }
};

const startHideTimer = () => {
    clearHideTimer();
    hideTimer = setTimeout(() => {
        show.value = false;
    }, 3000);
};

const getScrollInfo = (target: EventTarget | null): { scrollTop: number; scrollHeight: number; clientHeight: number; element: Element | Window } | null => {
    if (!target) return null;

    if (target === window || target === document) {
        return {
            scrollTop: window.scrollY,
            scrollHeight: document.documentElement.scrollHeight,
            clientHeight: window.innerHeight,
            element: window,
        };
    }

    const el = target as Element;
    // Only track elements that actually have overflow scroll/auto
    if (el.scrollHeight > el.clientHeight + 10) {
        return {
            scrollTop: el.scrollTop,
            scrollHeight: el.scrollHeight,
            clientHeight: el.clientHeight,
            element: el,
        };
    }

    return null;
};

const canScrollDown = (info: { scrollTop: number; scrollHeight: number; clientHeight: number }): boolean => {
    const remaining = info.scrollHeight - info.scrollTop - info.clientHeight;
    return remaining > 10;
};

const onScroll = (event: Event) => {
    const info = getScrollInfo(event.target);
    if (!info) return;

    if (info.scrollTop > SCROLL_THRESHOLD) {
        activeTarget = info.element;
        show.value = true;
        clearHideTimer();

        // If we can't scroll further down, start hide timer
        if (!canScrollDown(info)) {
            startHideTimer();
        }
    } else {
        // Only hide if this is the same container that showed the button
        if (activeTarget === info.element || activeTarget === null) {
            show.value = false;
            clearHideTimer();
        }
    }
};

const scrollToTop = () => {
    if (activeTarget && activeTarget instanceof Element) {
        activeTarget.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    startHideTimer();
};

onMounted(() => {
    // Use capture to intercept scroll on any element, including inner containers
    document.addEventListener('scroll', onScroll, { passive: true, capture: true });

    if (window.scrollY > SCROLL_THRESHOLD) {
        activeTarget = window;
        show.value = true;
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('scroll', onScroll, true);
    clearHideTimer();
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-4"
    >
        <Button
            v-if="show"
            variant="outline"
            size="icon"
            class="fixed bottom-6 right-6 z-50 h-10 w-10 rounded-full shadow-lg cursor-pointer"
            @click="scrollToTop"
        >
            <ArrowUp class="h-5 w-5" />
        </Button>
    </Transition>
</template>

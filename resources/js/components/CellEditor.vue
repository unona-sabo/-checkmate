<script setup lang="ts">
import { ref, watch, onBeforeUnmount, onMounted } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import CellColorPicker from './CellColorPicker.vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        readonly?: boolean;
        fontWeight?: string;
        fontColor?: string;
    }>(),
    {
        readonly: false,
        fontWeight: '',
        fontColor: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    focus: [];
    blur: [];
}>();

const isEditing = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const showToolbar = ref(false);
const toolbarStyle = ref({ top: '0px', left: '0px' });
const toolbarRef = ref<HTMLElement | null>(null);

const wrapPlainText = (value: string): string => {
    if (!value) return '<p></p>';
    if (/<[a-z][\s\S]*>/i.test(value)) return value;
    return `<p>${value.replace(/\n/g, '</p><p>')}</p>`;
};

const updateToolbarPosition = () => {
    if (!editor.value || !containerRef.value) {
        showToolbar.value = false;
        return;
    }

    const { from, to, empty } = editor.value.state.selection;
    if (empty || from === to) {
        showToolbar.value = false;
        return;
    }

    // Get the DOM coordinates of the selection
    const view = editor.value.view;
    const start = view.coordsAtPos(from);
    const end = view.coordsAtPos(to);
    const containerRect = containerRef.value.getBoundingClientRect();

    const centerX = (start.left + end.right) / 2 - containerRect.left;
    const topY = start.top - containerRect.top - 40;

    toolbarStyle.value = {
        top: `${topY}px`,
        left: `${centerX}px`,
    };
    showToolbar.value = true;
};

const editor = useEditor({
    content: wrapPlainText(props.modelValue),
    editable: !props.readonly,
    extensions: [
        StarterKit.configure({
            heading: false,
            bulletList: false,
            orderedList: false,
            listItem: false,
            blockquote: false,
            codeBlock: false,
            code: false,
            horizontalRule: false,
        }),
        TextStyle,
        Color,
    ],
    editorProps: {
        attributes: {
            class: 'outline-none w-full cell-editor-content',
        },
    },
    onFocus() {
        isEditing.value = true;
        emit('focus');
    },
    onBlur({ event }) {
        // Don't close if clicking inside the toolbar
        const relatedTarget = event?.relatedTarget as HTMLElement | null;
        if (relatedTarget && toolbarRef.value?.contains(relatedTarget)) {
            return;
        }
        if (containerRef.value?.contains(relatedTarget as Node)) {
            return;
        }

        isEditing.value = false;
        showToolbar.value = false;
        if (editor.value) {
            const html = editor.value.getHTML();
            if (html !== wrapPlainText(props.modelValue)) {
                emit('update:modelValue', html);
            }
        }
        emit('blur');
    },
    onSelectionUpdate() {
        updateToolbarPosition();
    },
});

watch(
    () => props.modelValue,
    (newVal) => {
        if (!editor.value) return;
        const currentHtml = editor.value.getHTML();
        const wrapped = wrapPlainText(newVal);
        if (currentHtml !== wrapped) {
            editor.value.commands.setContent(wrapped, false);
        }
    },
);

watch(
    () => props.readonly,
    (val) => {
        editor.value?.setEditable(!val);
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const getFontWeightClass = (weight?: string): string => {
    switch (weight) {
        case 'bold':
            return 'font-bold';
        case 'semibold':
            return 'font-semibold';
        case 'medium':
            return 'font-medium';
        case 'light':
            return 'font-light';
        default:
            return 'font-normal';
    }
};

const setColor = (color: string | null) => {
    if (!editor.value) return;
    if (color) {
        editor.value.chain().focus().setColor(color).run();
    } else {
        editor.value.chain().focus().unsetColor().run();
    }
};

const currentTextColor = ref<string | null>(null);

watch(
    () => editor.value?.isActive('textStyle'),
    () => {
        if (editor.value) {
            currentTextColor.value =
                editor.value.getAttributes('textStyle').color || null;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        ref="containerRef"
        class="relative min-h-[28px] w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm break-words whitespace-pre-wrap shadow-xs"
        :class="[
            getFontWeightClass(fontWeight),
            isEditing ? 'ring-1 ring-ring' : '',
        ]"
        :style="{ color: fontColor || 'inherit' }"
    >
        <EditorContent v-if="editor" :editor="editor" />

        <div
            v-if="showToolbar && editor && !readonly"
            ref="toolbarRef"
            class="absolute z-50 flex -translate-x-1/2 items-center gap-0.5 rounded-lg border bg-popover p-1 shadow-md"
            :style="toolbarStyle"
            @mousedown.prevent
        >
            <button
                type="button"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded text-sm font-bold hover:bg-accent"
                :class="{ 'bg-accent': editor.isActive('bold') }"
                title="Bold"
                @click="editor.chain().focus().toggleBold().run()"
            >
                B
            </button>
            <button
                type="button"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded text-sm italic hover:bg-accent"
                :class="{ 'bg-accent': editor.isActive('italic') }"
                title="Italic"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                I
            </button>
            <button
                type="button"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded text-sm line-through hover:bg-accent"
                :class="{ 'bg-accent': editor.isActive('strike') }"
                title="Strikethrough"
                @click="editor.chain().focus().toggleStrike().run()"
            >
                S
            </button>
            <div class="mx-0.5 h-5 w-px bg-border" />
            <CellColorPicker
                :current-color="currentTextColor"
                @select="setColor"
            />
        </div>
    </div>
</template>

<style>
.cell-editor-content p {
    margin: 0;
    line-height: 1.5;
}

.cell-editor-content .tiptap {
    outline: none;
}

.cell-editor-content .tiptap p {
    margin: 0;
}
</style>

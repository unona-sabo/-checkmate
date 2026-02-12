<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { watch } from 'vue';
import axios from 'axios';
import {
    Bold,
    Italic,
    Strikethrough,
    Code,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    Minus,
    Undo,
    Redo,
    Link as LinkIcon,
    Unlink,
    ImagePlus,
} from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    uploadUrl?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const uploadImage = async (file: File): Promise<string | null> => {
    if (!props.uploadUrl) return null;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(props.uploadUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data.url;
    } catch {
        return null;
    }
};

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit,
        Image.configure({
            HTMLAttributes: {
                class: 'rounded-md max-w-full',
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary underline',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder || 'Write your content here...',
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none min-h-[300px] px-4 py-3',
        },
        handlePaste(view, event) {
            const items = event.clipboardData?.items;
            if (!items) return false;

            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    event.preventDefault();
                    const file = item.getAsFile();
                    if (file) {
                        handleImageUpload(file);
                    }
                    return true;
                }
            }
            return false;
        },
        handleDrop(view, event) {
            const files = event.dataTransfer?.files;
            if (!files || files.length === 0) return false;

            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    event.preventDefault();
                    handleImageUpload(file);
                    return true;
                }
            }
            return false;
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

const handleImageUpload = async (file: File) => {
    const url = await uploadImage(file);
    if (url && editor.value) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
};

const addImage = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files?.[0];
        if (file) {
            await handleImageUpload(file);
        }
    };
    input.click();
};

const setLink = () => {
    if (!editor.value) return;

    const previousUrl = editor.value.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    if (url === null) return;

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

watch(() => props.modelValue, (value) => {
    if (!editor.value) return;
    const isSame = editor.value.getHTML() === value;
    if (!isSame) {
        editor.value.commands.setContent(value || '', false);
    }
});
</script>

<template>
    <div class="rounded-md border border-input bg-background overflow-hidden">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-input bg-muted/30 px-2 py-1.5">
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('bold') }]"
                title="Bold"
            >
                <Bold class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('italic') }]"
                title="Italic"
            >
                <Italic class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('strike') }]"
                title="Strikethrough"
            >
                <Strikethrough class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleCode().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('code') }]"
                title="Code"
            >
                <Code class="h-4 w-4" />
            </button>

            <div class="w-px h-5 bg-border mx-1" />

            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('heading', { level: 1 }) }]"
                title="Heading 1"
            >
                <Heading1 class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('heading', { level: 2 }) }]"
                title="Heading 2"
            >
                <Heading2 class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('heading', { level: 3 }) }]"
                title="Heading 3"
            >
                <Heading3 class="h-4 w-4" />
            </button>

            <div class="w-px h-5 bg-border mx-1" />

            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('bulletList') }]"
                title="Bullet list"
            >
                <List class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('orderedList') }]"
                title="Ordered list"
            >
                <ListOrdered class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('blockquote') }]"
                title="Blockquote"
            >
                <Quote class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setHorizontalRule().run()"
                class="p-1.5 rounded hover:bg-muted transition-colors"
                title="Horizontal rule"
            >
                <Minus class="h-4 w-4" />
            </button>

            <div class="w-px h-5 bg-border mx-1" />

            <button
                type="button"
                @click="setLink"
                :class="['p-1.5 rounded hover:bg-muted transition-colors', { 'bg-muted text-primary': editor.isActive('link') }]"
                title="Add link"
            >
                <LinkIcon class="h-4 w-4" />
            </button>
            <button
                v-if="editor.isActive('link')"
                type="button"
                @click="editor.chain().focus().unsetLink().run()"
                class="p-1.5 rounded hover:bg-muted transition-colors"
                title="Remove link"
            >
                <Unlink class="h-4 w-4" />
            </button>
            <button
                v-if="uploadUrl"
                type="button"
                @click="addImage"
                class="p-1.5 rounded hover:bg-muted transition-colors"
                title="Insert image"
            >
                <ImagePlus class="h-4 w-4" />
            </button>

            <div class="w-px h-5 bg-border mx-1" />

            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().undo()"
                class="p-1.5 rounded hover:bg-muted transition-colors disabled:opacity-30"
                title="Undo"
            >
                <Undo class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().redo()"
                class="p-1.5 rounded hover:bg-muted transition-colors disabled:opacity-30"
                title="Redo"
            >
                <Redo class="h-4 w-4" />
            </button>
        </div>

        <!-- Editor -->
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: var(--muted-foreground);
    pointer-events: none;
    height: 0;
}

.tiptap {
    min-height: 300px;
}

.tiptap:focus {
    outline: none;
}

.tiptap h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.tiptap h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
}

.tiptap h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}

.tiptap ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

.tiptap ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

.tiptap li {
    margin: 0.125rem 0;
}

.tiptap blockquote {
    border-left: 3px solid var(--border);
    padding-left: 1rem;
    margin: 0.5rem 0;
    color: var(--muted-foreground);
}

.tiptap code {
    background: var(--muted);
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-size: 0.875rem;
    font-family: ui-monospace, monospace;
}

.tiptap pre {
    background: var(--muted);
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
    margin: 0.5rem 0;
    overflow-x: auto;
}

.tiptap pre code {
    background: transparent;
    padding: 0;
}

.tiptap hr {
    border-color: var(--border);
    margin: 1rem 0;
}

.tiptap img {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
    margin: 0.5rem 0;
}

.tiptap a {
    color: var(--primary);
    text-decoration: underline;
}

.tiptap p {
    margin: 0.25rem 0;
}
</style>

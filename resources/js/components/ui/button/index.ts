import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all cursor-pointer disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-gradient-to-r from-primary to-primary/80 text-primary-foreground hover:from-primary/90 hover:to-primary/70 shadow-md",
        cta:
          "bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-400 text-blue-800 hover:from-amber-300 hover:via-yellow-200 hover:to-amber-300 font-semibold shadow-md shadow-amber-500/20",
        destructive:
          "bg-gradient-to-r from-destructive to-red-600 text-white hover:from-destructive/90 hover:to-red-500 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 shadow-md",
        outline:
          "border bg-gradient-to-b from-background to-muted/30 shadow-xs hover:from-amber-100 hover:to-yellow-50 hover:border-amber-300 hover:text-amber-800 dark:from-input/30 dark:to-input/10 dark:border-input dark:hover:from-amber-500/20 dark:hover:to-yellow-500/10 dark:hover:border-amber-500/50 dark:hover:text-amber-300",
        secondary:
          "bg-gradient-to-r from-secondary to-secondary/80 text-secondary-foreground hover:from-amber-100 hover:to-yellow-50 hover:text-amber-800 dark:hover:from-amber-500/20 dark:hover:to-yellow-500/10 dark:hover:text-amber-300",
        ghost:
          "hover:bg-gradient-to-r hover:from-amber-100 hover:to-yellow-100 hover:text-amber-800 dark:hover:from-amber-500/30 dark:hover:to-yellow-500/20 dark:hover:text-amber-300",
        link: "text-primary underline-offset-4 hover:underline",
        "gradient-primary":
          "bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 text-white hover:from-blue-500 hover:via-blue-400 hover:to-indigo-500 shadow-lg shadow-blue-500/30",
        "gradient-success":
          "bg-gradient-to-r from-emerald-500 via-green-500 to-teal-500 text-white hover:from-emerald-400 hover:via-green-400 hover:to-teal-400 shadow-lg shadow-green-500/30",
        "gradient-purple":
          "bg-gradient-to-r from-purple-600 via-violet-500 to-indigo-600 text-white hover:from-purple-500 hover:via-violet-400 hover:to-indigo-500 shadow-lg shadow-purple-500/30",
      },
      size: {
        "default": "h-8 px-3 py-1.5 has-[>svg]:px-2.5",
        "sm": "h-7 rounded-md gap-1.5 px-2.5 has-[>svg]:px-2",
        "lg": "h-9 rounded-md px-4 has-[>svg]:px-3",
        "icon": "size-8",
        "icon-sm": "size-7",
        "icon-lg": "size-9",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>

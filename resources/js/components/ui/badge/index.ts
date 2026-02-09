import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-gradient-to-r from-primary to-primary/80 text-primary-foreground [a&]:hover:from-primary/90 [a&]:hover:to-primary/70",
        secondary:
          "border-transparent bg-gradient-to-r from-secondary to-secondary/80 text-secondary-foreground [a&]:hover:from-secondary/90 [a&]:hover:to-secondary/70",
        destructive:
          "border-transparent bg-gradient-to-r from-destructive to-red-600 text-white [a&]:hover:from-destructive/90 [a&]:hover:to-red-500 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "text-foreground [a&]:hover:bg-gradient-to-r [a&]:hover:from-accent/20 [a&]:hover:to-accent/10 [a&]:hover:text-accent-foreground",
        success:
          "border-transparent bg-gradient-to-r from-emerald-500 to-green-500 text-white",
        warning:
          "border-transparent bg-gradient-to-r from-amber-400 to-yellow-400 text-amber-900",
        info:
          "border-transparent bg-gradient-to-r from-blue-500 to-cyan-500 text-white",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>

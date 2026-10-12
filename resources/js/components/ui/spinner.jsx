import { Loader2Icon } from "lucide-react"

import { cn } from "@/lib/utils"

/**
 * @param {React.ComponentProps<"svg">} props
 */
function Spinner({ className, ...props }) {
  return (
    <Loader2Icon
      role="status"
      aria-label="Memuat"
      className={cn("size-4 animate-spin", className)}
      {...props}
    />
  )
}

export { Spinner }

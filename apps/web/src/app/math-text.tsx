import type { ReactNode } from "react";

type MathTextProps = {
  children: ReactNode;
  className?: string;
};

export default function MathText({ children, className }: MathTextProps) {
  return (
    <span
      className={className}
      dir="ltr"
      data-modrik-math-text="true"
      style={{ unicodeBidi: "isolate" }}
    >
      {children}
    </span>
  );
}

import type { ReactNode } from "react";

import type { Locale } from "@/lib/learning-api";

type MixedDirectionTextProps = {
  children: ReactNode;
  className?: string;
};

export function MixedDirectionText({ children, className }: MixedDirectionTextProps) {
  return (
    <bdi
      className={className}
      dir="auto"
      data-modrik-mixed-direction="true"
      style={{ unicodeBidi: "isolate" }}
    >
      {children}
    </bdi>
  );
}

export function LocalizedQuestionText({
  text,
  locale,
}: {
  text: string;
  locale: Locale;
}) {
  return (
    <span
      lang={locale}
      dir={locale === "ar" ? "rtl" : "ltr"}
      data-modrik-question-text="true"
    >
      <MixedDirectionText>{text}</MixedDirectionText>
    </span>
  );
}

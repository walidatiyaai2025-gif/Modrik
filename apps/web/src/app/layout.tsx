import type { Metadata } from "next";
import type { ReactNode } from "react";
import { resolveRuntimeInspectorConfig } from "../lib/runtime-inspector-config";
import RuntimeInspector from "./runtime-inspector";
import "./globals.css";
import "./auth.css";

export const metadata: Metadata = {
  title: "MODRIK | مُدرك",
  description: "MODRIK student web application.",
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  const inspector = resolveRuntimeInspectorConfig();
  return (
    <html lang="en" dir="ltr" className="h-full antialiased">
      <body className="flex min-h-full flex-col">
        {children}
        <RuntimeInspector {...inspector} />
      </body>
    </html>
  );
}

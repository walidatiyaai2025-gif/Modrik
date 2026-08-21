import type { Metadata } from "next";
import { headers } from "next/headers";
import type { ReactNode } from "react";
import { resolveRuntimeInspectorConfig } from "../lib/runtime-inspector-config";
import RuntimeInspector from "./runtime-inspector";
import "./globals.css";
import "./learning-responsive-closeout.css";
import "./auth.css";
import "./landing.css";

export const metadata: Metadata = {
  title: "MODRIK | مُدرك",
  description: "MODRIK learning platform for students and system administrators.",
};

export default async function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  // The production CSP nonce is generated per request in proxy.ts. Keep the
  // App Router tree request-bound so Next can read that CSP and nonce its
  // hydration scripts instead of serving a static shell that strict CSP blocks.
  await headers();

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

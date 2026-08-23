import type { Metadata } from "next";
import { headers } from "next/headers";
import type { ReactNode } from "react";
import { resolveRuntimeInspectorConfig } from "../lib/runtime-inspector-config";
import RuntimeInspector from "./runtime-inspector";
import "./globals.css";
import "./learning-responsive-closeout.css";
import "./auth.css";
import "./landing.css";
import "./portal-runtime.css";
import "./release-badge.css";

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
  // MODRIK_RELEASE_SHA is intentionally server-runtime owned. NEXT_PUBLIC_*
  // remains a compatibility fallback for older build/deploy paths, but Next can
  // inline public variables during compilation and must not be the authoritative
  // cPanel release identity.
  const release =
    process.env.MODRIK_RELEASE_SHA?.trim() || process.env.NEXT_PUBLIC_MODRIK_RELEASE_SHA?.trim();
  const shortRelease = release ? release.slice(0, 12) : null;

  return (
    <html lang="en" dir="ltr" className="h-full antialiased">
      <body className="flex min-h-full flex-col">
        {release && shortRelease ? (
          <div
            className="modrik-release-badge"
            data-testid="modrik-web-release-badge"
            data-modrik-release-sha={release}
            data-modrik-release-short={shortRelease}
            title={`MODRIK deployed release: ${release}`}
            aria-label={`MODRIK build ${shortRelease}`}
          >
            Build {shortRelease}
          </div>
        ) : null}
        {children}
        <RuntimeInspector {...inspector} />
      </body>
    </html>
  );
}

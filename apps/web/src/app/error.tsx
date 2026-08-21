"use client";

import { useEffect } from "react";
import { recordBrowserException } from "../lib/runtime-diagnostics";

export default function AppError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    recordBrowserException("react", error);
  }, [error]);

  return (
    <main role="alert" className="min-h-screen p-8">
      <h1>MODRIK | مُدرك</h1>
      <p>
        This screen could not be completed. · تعذر إكمال هذه الشاشة. · Cet écran n’a pas pu être chargé.
      </p>
      <button type="button" onClick={reset}>
        Try again · حاول مرة أخرى · Réessayer
      </button>
    </main>
  );
}

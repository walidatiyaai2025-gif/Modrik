export type RuntimeInspectorConfig = {
  enabled: boolean;
  environment: string;
  build: string;
  commit: string;
};

function safeIdentity(value: string | undefined, max: number): string {
  const candidate = value?.trim() ?? "";
  if (!candidate || candidate.length > max) return "unknown";
  return /^[A-Za-z0-9._:/ -]+$/.test(candidate) ? candidate : "unknown";
}

export function resolveRuntimeInspectorConfig(
  env: Record<string, string | undefined> = process.env,
): RuntimeInspectorConfig {
  const environment = safeIdentity(env.MODRIK_RUNTIME_ENVIRONMENT, 48).toLowerCase();
  const nonProductionEnvironments = new Set(["development", "dev", "test", "staging", "pilot", "demo"]);
  return {
    enabled: env.MODRIK_RUNTIME_INSPECTOR_ENABLED === "true" && nonProductionEnvironments.has(environment),
    environment,
    build: safeIdentity(env.MODRIK_BUILD_VERSION, 64),
    commit: safeIdentity(env.MODRIK_GIT_SHA, 64),
  };
}

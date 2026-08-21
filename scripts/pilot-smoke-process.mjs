import { spawnSync } from "node:child_process";

export function runBoundedSync(command, args, { timeoutMs, ...options }) {
  if (!Number.isInteger(timeoutMs) || timeoutMs <= 0) {
    throw new TypeError(`timeoutMs must be a positive integer; received ${timeoutMs}`);
  }

  const result = spawnSync(command, args, {
    ...options,
    timeout: timeoutMs,
    killSignal: "SIGTERM",
  });

  return {
    result,
    timedOut: result.error?.code === "ETIMEDOUT",
    timeoutMs,
  };
}

export function boundedFailureDetail(execution, label) {
  const { result, timedOut, timeoutMs } = execution;
  if (timedOut) {
    return `${label} exceeded the ${formatDuration(timeoutMs)} timeout and was terminated.`;
  }
  if (result.error) {
    return `${label} could not complete: ${result.error.message}`;
  }
  if (result.signal) {
    return `${label} terminated by signal ${result.signal}.`;
  }
  if (result.status !== 0) {
    return `${label} exited with code ${result.status ?? 1}.`;
  }
  return null;
}

function formatDuration(milliseconds) {
  if (milliseconds % 60_000 === 0) return `${milliseconds / 60_000} minute(s)`;
  if (milliseconds % 1_000 === 0) return `${milliseconds / 1_000} second(s)`;
  return `${milliseconds} ms`;
}

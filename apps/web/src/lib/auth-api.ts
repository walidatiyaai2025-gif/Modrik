export type Locale = "ar" | "en" | "fr";
export type Provider = "google" | "apple";

export type SessionIdentity = {
  user_id: string;
  locale: Locale;
  roles: string[];
};

export type AccountSummary = {
  id: string;
  email: string | null;
  email_verified: boolean;
  password_enabled: boolean;
  status: string;
};

export type AuthSession = {
  id: string;
  name: string | null;
  authenticated_at: string;
  last_used_at: string;
  expires_at: string;
  created_at?: string;
  is_current: boolean;
};

export type AuthResult = {
  account: AccountSummary;
  session: AuthSession;
};

export type ProviderIntent = {
  state: string;
  nonce: string;
  expires_at: string;
};

type Envelope<T> = { data: T; meta: { request_id: string } };
type ProblemDetails = {
  status?: number;
  code?: string;
  detail?: string;
  retryable?: boolean;
  retry_after_seconds?: number;
  errors?: Array<{ pointer?: string; code?: string; message?: string }>;
};

export class AuthApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly retryable: boolean,
    public readonly retryAfterSeconds?: number,
  ) {
    super(message);
    this.name = "AuthApiError";
  }
}

async function requestData<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api/auth/${path}`, {
    ...init,
    headers: {
      Accept: "application/json, application/problem+json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...init?.headers,
    },
    cache: "no-store",
  });

  if (response.status === 204) return undefined as T;

  const payload: unknown = await response.json();
  if (!response.ok) {
    const problem = payload as ProblemDetails;
    throw new AuthApiError(
      response.status,
      problem.code ?? "AUTH_REQUEST_FAILED",
      problem.detail ?? "The account request failed.",
      problem.retryable ?? response.status >= 500,
      problem.retry_after_seconds,
    );
  }

  return (payload as Envelope<T>).data;
}

function json(method: "POST" | "PUT" | "DELETE", body?: object): RequestInit {
  return {
    method,
    body: body === undefined ? undefined : JSON.stringify(body),
  };
}

export const authApi = {
  session: () => requestData<SessionIdentity>("session"),
  register: (name: string, email: string, password: string) =>
    requestData<AuthResult>("register", json("POST", { name, email, password })),
  login: (email: string, password: string) =>
    requestData<AuthResult>("login", json("POST", { email, password })),
  verifyEmail: (token: string) => requestData<void>("email/verify", json("POST", { token })),
  resendVerification: () => requestData<{ status: "accepted" }>("email/verification", json("POST")),
  requestRecovery: (email: string) =>
    requestData<{ status: "accepted" }>("password/recovery", json("POST", { email })),
  resetPassword: (token: string, password: string) =>
    requestData<void>("password/reset", json("POST", { token, password })),
  reauthenticate: (password: string) =>
    requestData<void>("reauthenticate", json("POST", { password })),
  changePassword: (currentPassword: string, newPassword: string) =>
    requestData<void>("password", json("PUT", { current_password: currentPassword, new_password: newPassword })),
  sessions: () => requestData<{ sessions: AuthSession[] }>("sessions"),
  logoutCurrent: () => requestData<void>("sessions/current", json("DELETE")),
  revokeOthers: () => requestData<void>("sessions/others", json("DELETE")),
  revokeAll: () => requestData<void>("sessions", json("DELETE")),
  deleteAccount: () => requestData<void>("account", json("DELETE", { confirmation: "DELETE" })),
  providerIntent: (provider: Provider, purpose: "login" | "link") =>
    requestData<ProviderIntent>(`providers/${provider}/${purpose}-intents`, json("POST")),
  providerCallback: (provider: Provider, state: string, idToken: string) =>
    requestData<AuthResult | { provider: Provider; linked: true; account_id: string }>(
      `providers/${provider}/callback`,
      json("POST", { state, id_token: idToken }),
    ),
  unlinkProvider: (provider: Provider) => requestData<void>(`providers/${provider}`, json("DELETE")),
};

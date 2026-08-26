import AuthWorkspace from "../auth-workspace";
import NotificationShortcut from "./notification-shortcut";

// Terminal closure provenance: this entrypoint is intentionally covered by both Student Portal and Notification Center exact-head browser gates.
export default function StudentPortalPage() {
  return (
    <div className="student-portal-boundary" data-testid="modrik-student-portal">
      <AuthWorkspace />
      <NotificationShortcut />
    </div>
  );
}
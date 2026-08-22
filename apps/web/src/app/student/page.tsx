import AuthWorkspace from "../auth-workspace";
import NotificationShortcut from "./notification-shortcut";

export default function StudentPortalPage() {
  return (
    <div className="student-portal-boundary" data-testid="modrik-student-portal">
      <AuthWorkspace />
      <NotificationShortcut />
    </div>
  );
}

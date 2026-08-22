import AuthWorkspace from "../auth-workspace";
import NotificationShortcut from "./notification-shortcut";

export default function StudentPortalPage() {
  return (
    <>
      <span data-testid="modrik-student-portal-route" data-portal="student" hidden />
      <AuthWorkspace />
      <NotificationShortcut />
    </>
  );
}

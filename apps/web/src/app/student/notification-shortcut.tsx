import Link from "next/link";
import styles from "./notification-shortcut.module.css";

export default function NotificationShortcut() {
  return (
    <Link
      href="/student/notifications"
      className={styles.shortcut}
      data-testid="modrik-student-notification-shortcut"
      aria-label="Notifications · الإشعارات"
    >
      <span className={styles.icon} aria-hidden="true">●</span>
      <span>Notifications · الإشعارات</span>
    </Link>
  );
}

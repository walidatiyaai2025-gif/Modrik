import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/notification_center.dart';
import 'package:modrik_mobile/src/student_notifications.dart';

void main() {
  test('notification models preserve Backend-owned localized state', () {
    final inbox = StudentNotificationInbox.fromJson({
      'unread_count': 1,
      'items': [
        {
          'id': '01J00000000000000000000099',
          'kind': 'learning_reminder',
          'title': {'ar': 'تابع الدرس', 'en': 'Continue', 'fr': 'Continuer'},
          'body': {'ar': 'جاهز', 'en': 'Ready', 'fr': 'Prêt'},
          'action': 'study',
          'occurred_at': '2026-08-22T12:00:00Z',
          'read_at': null,
          'is_read': false,
        },
      ],
    });

    expect(inbox.unreadCount, 1);
    expect(inbox.items, hasLength(1));
    expect(localize(inbox.items.single.title, ModrikLocale.ar), 'تابع الدرس');
    expect(inbox.items.single.action, 'study');
    expect(inbox.items.single.isRead, isFalse);
  });

  testWidgets('Notification Center renders empty, read and multilingual states', (tester) async {
    final gateway = _FakeNotificationGateway(
      const StudentNotificationInbox(items: [], unreadCount: 0),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: MobileNotificationCenter(
          gateway: gateway,
          initialLocale: ModrikLocale.en,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('modrik-mobile-notification-center')), findsOneWidget);
    expect(find.text('No notifications'), findsOneWidget);

    gateway.inboxValue = StudentNotificationInbox(
      unreadCount: 1,
      items: [
        StudentNotification(
          id: '01J00000000000000000000098',
          kind: 'progress_update',
          title: localizedTextFromJson({
            'ar': 'تقدم جديد',
            'en': 'Progress update',
            'fr': 'Mise à jour de progression',
          }),
          body: localizedTextFromJson({
            'ar': 'راجع تقدمك.',
            'en': 'Review your progress.',
            'fr': 'Consultez votre progression.',
          }),
          action: 'progress',
          occurredAt: '2026-08-22T12:00:00Z',
          readAt: null,
          isRead: false,
        ),
      ],
    );

    await tester.drag(find.byType(ListView), const Offset(0, 300));
    await tester.pump();
    await tester.fling(find.byType(ListView), const Offset(0, 300), 1000);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Retry'), warnIfMissed: false);

    // Recreate with populated server state to keep the test deterministic.
    await tester.pumpWidget(
      MaterialApp(
        home: MobileNotificationCenter(
          gateway: gateway,
          initialLocale: ModrikLocale.ar,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('تقدم جديد'), findsOneWidget);
    expect(find.textContaining('غير المقروء'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('تقدم جديد'))), TextDirection.rtl);

    await tester.tap(find.text('تحديد كمقروء'));
    await tester.pumpAndSettle();
    expect(gateway.markReadCalls, 1);
  });

  testWidgets('Notification Center exposes retry on offline failure', (tester) async {
    final gateway = _FailingNotificationGateway();
    await tester.pumpWidget(
      MaterialApp(
        home: MobileNotificationCenter(
          gateway: gateway,
          initialLocale: ModrikLocale.fr,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Une connexion est nécessaire pour actualiser les notifications.'),
      findsOneWidget,
    );
    expect(find.text('Réessayer'), findsOneWidget);
  });
}

class _FakeNotificationGateway implements StudentNotificationGateway {
  _FakeNotificationGateway(this.inboxValue);

  StudentNotificationInbox inboxValue;
  int markReadCalls = 0;

  @override
  bool get isConfigured => true;

  @override
  Future<StudentNotificationInbox> inbox() async => inboxValue;

  @override
  Future<StudentNotification> markRead(String notificationId) async {
    markReadCalls += 1;
    final current = inboxValue.items.singleWhere((item) => item.id == notificationId);
    final updated = StudentNotification(
      id: current.id,
      kind: current.kind,
      title: current.title,
      body: current.body,
      action: current.action,
      occurredAt: current.occurredAt,
      readAt: '2026-08-22T12:01:00Z',
      isRead: true,
    );
    inboxValue = StudentNotificationInbox(items: [updated], unreadCount: 0);
    return updated;
  }

  @override
  Future<StudentNotificationReadAllResult> markAllRead() async {
    return const StudentNotificationReadAllResult(
      updatedCount: 0,
      unreadCount: 0,
    );
  }
}

class _FailingNotificationGateway implements StudentNotificationGateway {
  @override
  bool get isConfigured => true;

  @override
  Future<StudentNotificationInbox> inbox() async {
    throw const LearningFailure(
      status: 0,
      code: 'MOBILE_NOTIFICATION_OFFLINE',
      message: 'offline',
      retryable: true,
    );
  }

  @override
  Future<StudentNotification> markRead(String notificationId) =>
      throw UnimplementedError();

  @override
  Future<StudentNotificationReadAllResult> markAllRead() =>
      throw UnimplementedError();
}

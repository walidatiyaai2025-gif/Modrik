import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'learning_gateway.dart';
import 'mobile_learning_controller.dart';
import 'models.dart';
import 'student_notifications.dart';

enum NotificationCenterStatus { loading, ready, offline, error, permission }

class MobileNotificationLauncher extends StatelessWidget {
  const MobileNotificationLauncher({
    super.key,
    required this.controller,
    required this.gateway,
    required this.child,
  });

  final MobileLearningController controller;
  final StudentNotificationGateway gateway;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final canOpen = controller.session != null && gateway.isConfigured;
        if (!canOpen) return child;
        final copy = MobileNotificationCopy(controller.locale);
        return Stack(
          children: [
            Positioned.fill(child: child),
            PositionedDirectional(
              end: 16,
              bottom: 84,
              child: SafeArea(
                minimum: const EdgeInsets.all(4),
                child: Semantics(
                  button: true,
                  label: copy.notifications,
                  child: FloatingActionButton.small(
                    heroTag: 'modrik-student-notifications',
                    tooltip: copy.notifications,
                    backgroundColor: ModrikColors.navy,
                    foregroundColor: ModrikColors.white,
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => MobileNotificationCenter(
                            gateway: gateway,
                            initialLocale: controller.locale,
                          ),
                        ),
                      );
                    },
                    child: const Icon(Icons.notifications_none_outlined),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class MobileNotificationCenter extends StatefulWidget {
  const MobileNotificationCenter({
    super.key,
    required this.gateway,
    required this.initialLocale,
  });

  final StudentNotificationGateway gateway;
  final ModrikLocale initialLocale;

  @override
  State<MobileNotificationCenter> createState() =>
      _MobileNotificationCenterState();
}

class _MobileNotificationCenterState extends State<MobileNotificationCenter> {
  late ModrikLocale _locale;
  NotificationCenterStatus _status = NotificationCenterStatus.loading;
  StudentNotificationInbox _inbox = const StudentNotificationInbox(
    items: [],
    unreadCount: 0,
  );
  String? _busyId;
  String? _message;

  @override
  void initState() {
    super.initState();
    _locale = widget.initialLocale;
    Future<void>.microtask(_load);
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _status = NotificationCenterStatus.loading;
      _message = null;
    });
    try {
      final inbox = await widget.gateway.inbox();
      if (!mounted) return;
      setState(() {
        _inbox = inbox;
        _status = NotificationCenterStatus.ready;
      });
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _status = failure.isPermission
            ? NotificationCenterStatus.permission
            : failure.isNetwork
                ? NotificationCenterStatus.offline
                : NotificationCenterStatus.error;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _status = NotificationCenterStatus.error);
    }
  }

  Future<void> _markRead(StudentNotification notification) async {
    if (notification.isRead || _busyId != null) return;
    setState(() {
      _busyId = notification.id;
      _message = null;
    });
    try {
      final updated = await widget.gateway.markRead(notification.id);
      if (!mounted) return;
      final wasUnread = !notification.isRead;
      setState(() {
        _inbox = StudentNotificationInbox(
          items: _inbox.items
              .map((item) => item.id == updated.id ? updated : item)
              .toList(growable: false),
          unreadCount: wasUnread
              ? (_inbox.unreadCount - 1).clamp(0, _inbox.unreadCount)
              : _inbox.unreadCount,
        );
      });
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _status = failure.isPermission
            ? NotificationCenterStatus.permission
            : failure.isNetwork
                ? NotificationCenterStatus.offline
                : NotificationCenterStatus.error;
      });
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _markAllRead() async {
    if (_inbox.unreadCount == 0 || _busyId != null) return;
    setState(() {
      _busyId = 'all';
      _message = null;
    });
    try {
      final result = await widget.gateway.markAllRead();
      if (!mounted) return;
      final now = DateTime.now().toUtc().toIso8601String();
      setState(() {
        _inbox = StudentNotificationInbox(
          items: _inbox.items
              .map(
                (item) => item.isRead
                    ? item
                    : StudentNotification(
                        id: item.id,
                        kind: item.kind,
                        title: item.title,
                        body: item.body,
                        action: item.action,
                        occurredAt: item.occurredAt,
                        readAt: item.readAt ?? now,
                        isRead: true,
                      ),
              )
              .toList(growable: false),
          unreadCount: result.unreadCount,
        );
        _message = MobileNotificationCopy(_locale).markedAll;
      });
    } on LearningFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _status = failure.isPermission
            ? NotificationCenterStatus.permission
            : failure.isNetwork
                ? NotificationCenterStatus.offline
                : NotificationCenterStatus.error;
      });
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final copy = MobileNotificationCopy(_locale);
    final direction = _locale == ModrikLocale.ar
        ? TextDirection.rtl
        : TextDirection.ltr;
    return Directionality(
      textDirection: direction,
      child: Scaffold(
        appBar: AppBar(
          title: Text(copy.notifications),
          actions: [
            PopupMenuButton<ModrikLocale>(
              tooltip: copy.language,
              initialValue: _locale,
              onSelected: (locale) => setState(() => _locale = locale),
              itemBuilder: (_) => const [
                PopupMenuItem(value: ModrikLocale.ar, child: Text('العربية')),
                PopupMenuItem(value: ModrikLocale.en, child: Text('English')),
                PopupMenuItem(value: ModrikLocale.fr, child: Text('Français')),
              ],
              child: ConstrainedBox(
                constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
                child: Center(child: Text(_locale.code.toUpperCase())),
              ),
            ),
            const SizedBox(width: 8),
          ],
        ),
        body: SafeArea(child: _body(copy)),
      ),
    );
  }

  Widget _body(MobileNotificationCopy copy) {
    if (_status != NotificationCenterStatus.ready) {
      final title = switch (_status) {
        NotificationCenterStatus.loading => copy.loading,
        NotificationCenterStatus.offline => copy.offline,
        NotificationCenterStatus.permission => copy.permission,
        NotificationCenterStatus.error => copy.unavailable,
        NotificationCenterStatus.ready => copy.notifications,
      };
      return Semantics(
        liveRegion: true,
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_status == NotificationCenterStatus.loading)
                  const CircularProgressIndicator()
                else
                  const Icon(Icons.notifications_off_outlined, size: 42),
                const SizedBox(height: 16),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: ModrikColors.navy,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (_status != NotificationCenterStatus.loading) ...[
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _load,
                    child: Text(copy.retry),
                  ),
                ],
              ],
            ),
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        key: const Key('modrik-mobile-notification-center'),
        padding: const EdgeInsets.all(20),
        children: [
          Semantics(
            liveRegion: true,
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${copy.unread}: ${_inbox.unreadCount}',
                    style: const TextStyle(
                      color: ModrikColors.navy,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _inbox.unreadCount == 0 || _busyId != null
                      ? null
                      : _markAllRead,
                  child: Text(copy.markAll),
                ),
              ],
            ),
          ),
          if (_message case final message?) ...[
            const SizedBox(height: 8),
            Semantics(liveRegion: true, child: Text(message)),
          ],
          const SizedBox(height: 12),
          if (_inbox.items.isEmpty)
            _EmptyNotifications(copy: copy)
          else
            ..._inbox.items.map(
              (notification) => _NotificationCard(
                notification: notification,
                locale: _locale,
                copy: copy,
                busy: _busyId != null,
                onRead: () => _markRead(notification),
              ),
            ),
        ],
      ),
    );
  }
}

class _EmptyNotifications extends StatelessWidget {
  const _EmptyNotifications({required this.copy});

  final MobileNotificationCopy copy;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: copy.emptyTitle,
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: ModrikColors.white,
          borderRadius: BorderRadius.circular(ModrikRadii.medium),
          border: Border.all(color: ModrikColors.sky),
        ),
        child: Column(
          children: [
            const Icon(Icons.inbox_outlined, size: 40, color: ModrikColors.blue),
            const SizedBox(height: 12),
            Text(
              copy.emptyTitle,
              style: const TextStyle(
                color: ModrikColors.navy,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Text(copy.emptyBody, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.locale,
    required this.copy,
    required this.busy,
    required this.onRead,
  });

  final StudentNotification notification;
  final ModrikLocale locale;
  final MobileNotificationCopy copy;
  final bool busy;
  final VoidCallback onRead;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    localize(notification.title, locale),
                    style: const TextStyle(
                      color: ModrikColors.navy,
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Semantics(
                  label: notification.isRead ? copy.read : copy.unread,
                  child: Icon(
                    notification.isRead
                        ? Icons.done_all_outlined
                        : Icons.circle,
                    size: 18,
                    color: notification.isRead
                        ? ModrikColors.slate
                        : ModrikColors.teal,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(localize(notification.body, locale)),
            const SizedBox(height: 12),
            Text(
              notification.kind.replaceAll('_', ' '),
              style: const TextStyle(color: ModrikColors.slate, fontSize: 12),
            ),
            if (!notification.isRead) ...[
              const SizedBox(height: 12),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: OutlinedButton(
                  onPressed: busy ? null : onRead,
                  child: Text(copy.markRead),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class MobileNotificationCopy {
  const MobileNotificationCopy(this.locale);

  final ModrikLocale locale;

  String get notifications => _value(
        ar: 'الإشعارات',
        en: 'Notifications',
        fr: 'Notifications',
      );
  String get language => _value(
        ar: 'اللغة',
        en: 'Language',
        fr: 'Langue',
      );
  String get loading => _value(
        ar: 'جارٍ تحميل الإشعارات…',
        en: 'Loading notifications…',
        fr: 'Chargement des notifications…',
      );
  String get offline => _value(
        ar: 'الإشعارات تحتاج اتصالًا لتحديثها.',
        en: 'Notifications need a connection to refresh.',
        fr: 'Une connexion est nécessaire pour actualiser les notifications.',
      );
  String get permission => _value(
        ar: 'سجّل الدخول لعرض إشعاراتك.',
        en: 'Sign in to view your notifications.',
        fr: 'Connectez-vous pour voir vos notifications.',
      );
  String get unavailable => _value(
        ar: 'تعذر تحميل الإشعارات حاليًا.',
        en: 'Notifications are unavailable right now.',
        fr: 'Les notifications sont indisponibles pour le moment.',
      );
  String get retry => _value(ar: 'إعادة المحاولة', en: 'Retry', fr: 'Réessayer');
  String get unread => _value(ar: 'غير المقروء', en: 'Unread', fr: 'Non lues');
  String get read => _value(ar: 'مقروء', en: 'Read', fr: 'Lue');
  String get markAll => _value(
        ar: 'تحديد الكل كمقروء',
        en: 'Mark all read',
        fr: 'Tout marquer comme lu',
      );
  String get markRead => _value(
        ar: 'تحديد كمقروء',
        en: 'Mark read',
        fr: 'Marquer comme lue',
      );
  String get markedAll => _value(
        ar: 'تم تحديد كل الإشعارات كمقروءة.',
        en: 'All notifications were marked read.',
        fr: 'Toutes les notifications ont été marquées comme lues.',
      );
  String get emptyTitle => _value(
        ar: 'لا توجد إشعارات',
        en: 'No notifications',
        fr: 'Aucune notification',
      );
  String get emptyBody => _value(
        ar: 'ستظهر تحديثات مُدرك المهمة هنا عند توفرها.',
        en: 'Important MODRIK updates will appear here when available.',
        fr: 'Les mises à jour importantes de MODRIK apparaîtront ici.',
      );

  String _value({required String ar, required String en, required String fr}) {
    return switch (locale) {
      ModrikLocale.ar => ar,
      ModrikLocale.fr => fr,
      ModrikLocale.en => en,
    };
  }
}

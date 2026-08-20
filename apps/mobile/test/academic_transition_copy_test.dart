import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/academic_context_reset_boundary.dart';
import 'package:modrik_mobile/src/academic_track_catalogue.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  final cases = <_LocaleCopyCase>[
    const _LocaleCopyCase(
      locale: ModrikLocale.en,
      change: 'Change academic track',
      title: 'Before you change academic track',
      body:
          'Your previous academic track, attempts, and progress will be archived—not deleted. Any work still in progress may be left unfinished.',
      sync: 'Sync all pending answers and changes before you continue.',
      confirm: 'I understand what will happen when I change tracks.',
      cancel: 'Cancel',
      retry: 'Try again',
      permission:
          'We can’t load academic tracks with this session. Sign in again, then try again.',
      error:
          'We couldn’t load your academic tracks. Nothing has changed. Try again.',
      failure:
          'We couldn’t update your academic track. Nothing changed. Check your connection and that the selected track is still available, then try again.',
      direction: TextDirection.ltr,
    ),
    const _LocaleCopyCase(
      locale: ModrikLocale.ar,
      change: 'تغيير المسار الأكاديمي',
      title: 'قبل تغيير المسار الأكاديمي',
      body:
          'سيتم أرشفة مسارك الأكاديمي السابق ومحاولاتك وتقدّمك، ولن يتم حذفها. وقد يبقى أي عمل جارٍ غير مكتمل.',
      sync: 'زامن جميع الإجابات والتغييرات المعلّقة قبل المتابعة.',
      confirm: 'أفهم ما سيحدث عند تغيير المسار.',
      cancel: 'إلغاء',
      retry: 'حاول مرة أخرى',
      permission:
          'لا يمكن تحميل المسارات الأكاديمية بهذه الجلسة. سجّل الدخول من جديد ثم حاول مرة أخرى.',
      error: 'تعذر تحميل مساراتك الأكاديمية. لم يتغير شيء. حاول مرة أخرى.',
      failure:
          'تعذر تحديث مسارك الأكاديمي. لم يتغير شيء. تحقق من اتصالك ومن أن المسار المختار ما زال متاحًا، ثم حاول مرة أخرى.',
      direction: TextDirection.rtl,
    ),
    const _LocaleCopyCase(
      locale: ModrikLocale.fr,
      change: 'Changer de parcours académique',
      title: 'Avant de changer de parcours académique',
      body:
          'Votre ancien parcours académique, vos tentatives et votre progression seront archivés, pas supprimés. Tout travail en cours pourra rester inachevé.',
      sync:
          'Synchronisez toutes les réponses et modifications en attente avant de continuer.',
      confirm:
          'Je comprends ce qui se passera quand je changerai de parcours.',
      cancel: 'Annuler',
      retry: 'Réessayer',
      permission:
          'Cette session ne permet pas de charger vos parcours académiques. Reconnectez-vous à votre compte, puis réessayez.',
      error:
          'Nous n’avons pas pu charger vos parcours académiques. Rien n’a changé. Réessayez.',
      failure:
          'Nous n’avons pas pu mettre à jour votre parcours académique. Rien n’a changé. Vérifiez votre connexion et que le parcours choisi est toujours disponible, puis réessayez.',
      direction: TextDirection.ltr,
    ),
  ];

  testWidgets(
    'academic track change consequences stay learner-first and equivalent in AR EN FR',
    (tester) async {
      for (final copy in cases) {
        final controller = _controller(copy.locale, _CopyGateway());
        await tester.pumpWidget(_boundary(controller));
        await tester.pumpAndSettle();

        await tester.tap(find.text(copy.change));
        await tester.pumpAndSettle();

        expect(find.text(copy.title), findsOneWidget);
        expect(find.text(copy.body), findsOneWidget);
        expect(find.text(copy.sync), findsOneWidget);
        expect(find.text(copy.confirm), findsOneWidget);
        expect(
          Directionality.of(tester.element(find.text(copy.title))),
          copy.direction,
        );

        expect(find.textContaining('Backend'), findsNothing);
        expect(find.textContaining('backend'), findsNothing);
        expect(find.textContaining('same logical operation'), findsNothing);
        expect(find.textContaining('الخادم'), findsNothing);
        expect(find.textContaining('serveur'), findsNothing);

        await tester.tap(find.text(copy.cancel));
        await tester.pumpAndSettle();
      }
    },
  );

  testWidgets(
    'failed track change says nothing changed and gives a safe next step',
    (tester) async {
      final gateway = _CopyGateway(failReset: true);
      final controller = _controller(ModrikLocale.en, gateway);
      await tester.pumpWidget(_boundary(controller));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Change academic track'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.text('I understand what will happen when I change tracks.'),
      );
      await tester.pump();

      final dialog = find.byType(AlertDialog);
      final submit = find.descendant(
        of: dialog,
        matching: find.widgetWithText(FilledButton, 'Change academic track'),
      );
      await tester.tap(submit);
      await tester.pumpAndSettle();

      expect(controller.academicContext?.academicTrackId, _currentTrackId);
      expect(
        find.text(
          'We couldn’t update your academic track. Nothing changed. Check your connection and that the selected track is still available, then try again.',
        ),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'AR and FR catalogue failures keep retry reachable at 320px and 200 percent text',
    (tester) async {
      final semantics = tester.ensureSemantics();
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      tester.platformDispatcher.textScaleFactorTestValue = 2.0;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
        tester.platformDispatcher.clearTextScaleFactorTestValue();
        semantics.dispose();
      });

      for (final copy in cases.where((item) => item.locale != ModrikLocale.en)) {
        final failures = <LearningFailure>[
          const LearningFailure(
            status: 401,
            code: 'AUTHENTICATION_REQUIRED',
            message: 'Authentication required.',
            retryable: false,
          ),
          const LearningFailure(
            status: 503,
            code: 'LEARNING_REQUEST_FAILED',
            message: 'Unavailable.',
            retryable: true,
          ),
        ];

        for (final failure in failures) {
          final gateway = _CopyGateway(
            catalogueFailure: failure,
            catalogueFailuresBeforeSuccess: 1,
          );
          final controller = _controller(copy.locale, gateway);
          await tester.pumpWidget(_boundary(controller));
          await tester.pumpAndSettle();

          final expectedMessage = failure.isPermission
              ? copy.permission
              : copy.error;
          expect(find.text(expectedMessage), findsOneWidget);
          expect(
            Directionality.of(tester.element(find.text(expectedMessage))),
            copy.direction,
          );

          final retry = find.widgetWithText(OutlinedButton, copy.retry);
          expect(retry, findsOneWidget);
          final retryRect = tester.getRect(retry);
          expect(retryRect.left, greaterThanOrEqualTo(0));
          expect(retryRect.right, lessThanOrEqualTo(320));
          expect(retryRect.top, greaterThanOrEqualTo(0));
          expect(retryRect.bottom, lessThanOrEqualTo(640));
          expect(tester.takeException(), isNull);

          await tester.tap(retry);
          await tester.pumpAndSettle();
          expect(gateway.catalogueCalls, 2);
          expect(find.text(copy.change), findsOneWidget);
          expect(tester.takeException(), isNull);
        }
      }
    },
  );

  testWidgets(
    'AR and FR reset consequences and failure stay reachable at 320px and 200 percent text',
    (tester) async {
      final semantics = tester.ensureSemantics();
      tester.view.physicalSize = const Size(320, 640);
      tester.view.devicePixelRatio = 1;
      tester.platformDispatcher.textScaleFactorTestValue = 2.0;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
        tester.platformDispatcher.clearTextScaleFactorTestValue();
        semantics.dispose();
      });

      for (final copy in cases.where((item) => item.locale != ModrikLocale.en)) {
        final gateway = _CopyGateway(failReset: true);
        final controller = _controller(copy.locale, gateway);
        await tester.pumpWidget(_boundary(controller));
        await tester.pumpAndSettle();

        await tester.tap(find.text(copy.change));
        await tester.pumpAndSettle();

        final dialog = find.byType(AlertDialog);
        expect(dialog, findsOneWidget);
        expect(find.text(copy.title), findsOneWidget);
        expect(find.text(copy.body), findsOneWidget);
        expect(find.text(copy.sync), findsOneWidget);
        expect(tester.takeException(), isNull);

        final confirmation = find.text(copy.confirm);
        await tester.ensureVisible(confirmation);
        await tester.pumpAndSettle();
        await tester.tap(confirmation);
        await tester.pump();

        final submit = find.descendant(
          of: dialog,
          matching: find.widgetWithText(FilledButton, copy.change),
        );
        await tester.ensureVisible(submit);
        await tester.pumpAndSettle();
        await tester.tap(submit);
        await tester.pumpAndSettle();

        expect(controller.academicContext?.academicTrackId, _currentTrackId);
        final failureMessage = find.text(copy.failure);
        expect(failureMessage, findsOneWidget);
        await tester.ensureVisible(failureMessage);
        await tester.pumpAndSettle();
        final failureRect = tester.getRect(failureMessage);
        expect(failureRect.left, greaterThanOrEqualTo(0));
        expect(failureRect.right, lessThanOrEqualTo(320));
        expect(failureRect.top, greaterThanOrEqualTo(0));
        expect(failureRect.bottom, lessThanOrEqualTo(640));
        expect(tester.takeException(), isNull);

        await tester.tap(find.text(copy.cancel));
        await tester.pumpAndSettle();
      }
    },
  );
}

const _currentTrackId = '01J000000000000000000000A1';
const _otherTrackId = '01J000000000000000000000B2';

Widget _boundary(MobileLearningController controller) => MaterialApp(
      home: AcademicContextResetBoundary(
        controller: controller,
        child: const Scaffold(body: Text('Learning workspace')),
      ),
    );

MobileLearningController _controller(
  ModrikLocale locale,
  LearningGateway gateway,
) =>
    MobileLearningController(
      gateway: gateway,
      config: MobileBootstrapConfig(
        apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      ),
    )
      ..status = MobileViewStatus.ready
      ..locale = locale
      ..academicContext = _activeContext(_currentTrackId);

AcademicContext _activeContext(String trackId) => AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-current',
      'academic_track_id': trackId,
      'year_level': 'fixture-year',
      'activated_at': '2026-08-20T10:00:00Z',
    });

List<AcademicTrack> _tracks() => [
      AcademicTrack(
        id: _otherTrackId,
        labels: {
          ModrikLocale.ar: 'المسار الثاني',
          ModrikLocale.en: 'Second track',
          ModrikLocale.fr: 'Deuxième parcours',
        },
      ),
      AcademicTrack(
        id: _currentTrackId,
        labels: {
          ModrikLocale.ar: 'المسار الأول',
          ModrikLocale.en: 'First track',
          ModrikLocale.fr: 'Premier parcours',
        },
      ),
    ];

class _CopyGateway implements LearningGateway, AcademicTrackCatalogueGateway {
  _CopyGateway({
    this.failReset = false,
    this.catalogueFailure,
    this.catalogueFailuresBeforeSuccess = 0,
  });

  final bool failReset;
  final LearningFailure? catalogueFailure;
  final int catalogueFailuresBeforeSuccess;
  int catalogueCalls = 0;

  @override
  Future<List<AcademicTrack>> academicTracks() async {
    catalogueCalls += 1;
    if (catalogueFailure != null &&
        catalogueCalls <= catalogueFailuresBeforeSuccess) {
      throw catalogueFailure!;
    }
    return _tracks();
  }

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async =>
      _activeContext(academicTrackId);

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) async {
    if (failReset) {
      throw const LearningFailure(
        status: 404,
        code: 'RESOURCE_NOT_FOUND',
        message: 'Selected academic track is unavailable.',
        retryable: false,
      );
    }
    return _activeContext(academicTrackId);
  }

  @override
  Future<List<ProgressSnapshot>> progress() async => const [];

  @override
  Future<AcademicContext> academicContext() => throw UnimplementedError();

  @override
  Future<SavedAnswer> answer({
    required String attemptId,
    required String attemptQuestionId,
    required int expectedRevision,
    required Object? value,
    required String idempotencyKey,
  }) =>
      throw UnimplementedError();

  @override
  Future<Lesson> lesson(String lessonId) => throw UnimplementedError();

  @override
  Future<Attempt> resumeAttempt(String attemptId) => throw UnimplementedError();

  @override
  Future<Session> session() => throw UnimplementedError();

  @override
  Future<Attempt> startAttempt(String quizId, String idempotencyKey) =>
      throw UnimplementedError();

  @override
  Future<AttemptResult> submit(String attemptId, String idempotencyKey) =>
      throw UnimplementedError();
}

class _LocaleCopyCase {
  const _LocaleCopyCase({
    required this.locale,
    required this.change,
    required this.title,
    required this.body,
    required this.sync,
    required this.confirm,
    required this.cancel,
    required this.retry,
    required this.permission,
    required this.error,
    required this.failure,
    required this.direction,
  });

  final ModrikLocale locale;
  final String change;
  final String title;
  final String body;
  final String sync;
  final String confirm;
  final String cancel;
  final String retry;
  final String permission;
  final String error;
  final String failure;
  final TextDirection direction;
}

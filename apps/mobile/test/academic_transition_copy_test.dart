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
  _CopyGateway({this.failReset = false});

  final bool failReset;

  @override
  Future<List<AcademicTrack>> academicTracks() async => _tracks();

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
    required this.direction,
  });

  final ModrikLocale locale;
  final String change;
  final String title;
  final String body;
  final String sync;
  final String confirm;
  final String cancel;
  final TextDirection direction;
}

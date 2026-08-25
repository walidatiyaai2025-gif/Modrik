import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/academic_context_reset_boundary.dart';
import 'package:modrik_mobile/src/academic_track_catalogue.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  testWidgets(
    'Backend years are visible before tracks and changing year filters tracks at 320px 200 percent text',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 720));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      final gateway = _YearTrackGateway();
      final controller = MobileLearningController(
        gateway: gateway,
        config: MobileBootstrapConfig(
          apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
        ),
      )
        ..status = MobileViewStatus.ready
        ..locale = ModrikLocale.en
        ..academicContext =
            AcademicContext.fromJson({'state': 'onboarding_required'});

      await tester.pumpWidget(
        MaterialApp(
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(320, 720),
              textScaler: TextScaler.linear(2),
            ),
            child: AcademicContextResetBoundary(
              controller: controller,
              child: const SizedBox.shrink(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('School year'), findsOneWidget);
      expect(find.text('Academic track'), findsOneWidget);
      expect(find.text('Year 6'), findsOneWidget);
      expect(find.text('Science 6'), findsOneWidget);
      expect(find.text('Arts 6'), findsNothing);
      expect(find.text('Science 7'), findsNothing);

      final yearSelector =
          find.byKey(const ValueKey('academic-year-onboarding-en'));
      await tester.ensureVisible(yearSelector);
      await tester.pumpAndSettle();
      await tester.tap(yearSelector);
      await tester.pumpAndSettle();
      final year7 = find.text('Year 7').last;
      await tester.ensureVisible(year7);
      await tester.tap(year7);
      await tester.pumpAndSettle();

      expect(find.text('Science 7'), findsOneWidget);
      expect(find.text('Science 6'), findsNothing);

      controller.setLocale(ModrikLocale.ar);
      await tester.pumpAndSettle();
      expect(find.text('السنة الدراسية'), findsOneWidget);
      expect(find.text('المسار الأكاديمي'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.text('السنة الدراسية'))),
        TextDirection.rtl,
      );

      controller.setLocale(ModrikLocale.fr);
      await tester.pumpAndSettle();
      expect(find.text('Année scolaire'), findsOneWidget);
      expect(find.text('Parcours académique'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.text('Année scolaire'))),
        TextDirection.ltr,
      );
      expect(tester.takeException(), isNull);
    },
  );
}

class _YearTrackGateway implements LearningGateway, AcademicTrackCatalogueGateway {
  @override
  Future<List<AcademicTrack>> academicTracks() async => [
        AcademicTrack(
          id: '01J000000000000000000000A1',
          year: const AcademicYear(key: 'YEAR:6', label: 'Year 6'),
          labels: {
            ModrikLocale.ar: 'علوم 6',
            ModrikLocale.en: 'Science 6',
            ModrikLocale.fr: 'Sciences 6',
          },
        ),
        AcademicTrack(
          id: '01J000000000000000000000A2',
          year: const AcademicYear(key: 'YEAR:6', label: 'Year 6'),
          labels: {
            ModrikLocale.ar: 'آداب 6',
            ModrikLocale.en: 'Arts 6',
            ModrikLocale.fr: 'Lettres 6',
          },
        ),
        AcademicTrack(
          id: '01J000000000000000000000B1',
          year: const AcademicYear(key: 'YEAR:7', label: 'Year 7'),
          labels: {
            ModrikLocale.ar: 'علوم 7',
            ModrikLocale.en: 'Science 7',
            ModrikLocale.fr: 'Sciences 7',
          },
        ),
      ];

  @override
  Future<AcademicContext> academicContext() => throw UnimplementedError();

  @override
  Future<AcademicContext> activateAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) =>
      throw UnimplementedError();

  @override
  Future<AcademicContext> resetAcademicContext(
    String academicTrackId,
    String idempotencyKey,
  ) =>
      throw UnimplementedError();

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
  Future<List<ProgressSnapshot>> progress() async => const [];

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

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  testWidgets('student shell switches AR/EN/FR direction without a brand fork', (tester) async {
    final controller = _readyController();
    await tester.pumpWidget(ModrikApp(controller: controller, autoInitialize: false));

    expect(find.text('Dashboard'), findsWidgets);
    expect(find.text('Study'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('Dashboard').first)), TextDirection.ltr);

    controller.setLocale(ModrikLocale.ar);
    await tester.pump();

    expect(find.text('الرئيسية'), findsWidgets);
    expect(find.text('المذاكرة'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('الرئيسية').first)), TextDirection.rtl);

    controller.setLocale(ModrikLocale.fr);
    await tester.pump();
    expect(find.text('Tableau de bord'), findsWidgets);
    expect(Directionality.of(tester.element(find.text('Tableau de bord').first)), TextDirection.ltr);
  });

  testWidgets('practice exposes semantic questions and adequate choice touch targets', (tester) async {
    final semantics = tester.ensureSemantics();
    final controller = _readyController()..section = StudentSection.practice;
    await tester.pumpWidget(ModrikApp(controller: controller, autoInitialize: false));

    expect(find.bySemanticsLabel(RegExp(r'Question 1')), findsWidgets);
    expect(find.text('Server-order question one'), findsOneWidget);
    expect(find.text('Server-order question two'), findsOneWidget);

    final choiceInkWell = find.ancestor(
      of: find.text('Option B').first,
      matching: find.byType(InkWell),
    );
    expect(choiceInkWell, findsOneWidget);
    expect(tester.getSize(choiceInkWell).height, greaterThanOrEqualTo(48));

    await tester.tap(find.text('Option B').first);
    await tester.pump();
    expect(controller.answers['attempt-question-2'], 'option-b');
    semantics.dispose();
  });

  testWidgets('multiple-choice UI emits ordered JSON arrays instead of scalar strings', (tester) async {
    final controller = _readyController()..section = StudentSection.practice;
    await tester.pumpWidget(ModrikApp(controller: controller, autoInitialize: false));

    await tester.tap(find.text('Choice C'));
    await tester.pump();
    expect(controller.answers['attempt-question-3'], ['option-c']);

    await tester.tap(find.text('Choice A'));
    await tester.pump();
    expect(
      controller.answers['attempt-question-3'],
      ['option-a', 'option-c'],
      reason: 'array order follows the backend option snapshot, not tap order',
    );

    await tester.tap(find.text('Choice C'));
    await tester.pump();
    expect(controller.answers['attempt-question-3'], ['option-a']);
  });

  testWidgets('permission and offline states are explicit and retryable', (tester) async {
    final permissionController = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: const MobileBootstrapConfig(apiBaseUrl: null),
    );
    permissionController.status = MobileViewStatus.permission;
    await tester.pumpWidget(
      ModrikApp(
        key: const ValueKey('permission-app'),
        controller: permissionController,
        autoInitialize: false,
      ),
    );

    expect(find.text('Connection configuration required'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);

    final offlineController = _readyController();
    offlineController.status = MobileViewStatus.offline;
    await tester.pumpWidget(
      ModrikApp(
        key: const ValueKey('offline-app'),
        controller: offlineController,
        autoInitialize: false,
      ),
    );
    await tester.pump();

    expect(find.textContaining('You are offline'), findsOneWidget);
    expect(find.textContaining('exact learning snapshot'), findsOneWidget);
  });
}

MobileLearningController _readyController() {
  final controller = MobileLearningController(
    gateway: const UnconfiguredLearningGateway(),
    config: MobileBootstrapConfig(
      apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
      initialLessonId: 'lesson-1',
      academicTrackId: 'track-1',
    ),
  );
  controller
    ..status = MobileViewStatus.ready
    ..academicContext = AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-1',
      'academic_track_id': 'track-1',
      'year_level': 'Year 7',
      'activated_at': '2026-08-20T10:00:00Z',
    })
    ..lesson = Lesson.fromJson({
      'id': 'lesson-1',
      'curriculum_node_id': 'node-1',
      'content_version': 2,
      'title': {'en': 'Study lesson', 'ar': 'درس المذاكرة', 'fr': 'Leçon d’étude'},
      'practice_quiz_id': 'quiz-1',
      'blocks': [
        {
          'id': 'block-1',
          'position': 1,
          'type': 'paragraph',
          'content': {'en': 'Backend published lesson block', 'ar': 'محتوى منشور من الخادم', 'fr': 'Contenu publié par le serveur'},
        },
      ],
    })
    ..attempt = Attempt.fromJson({
      'id': 'attempt-1',
      'academic_context_id': 'context-1',
      'quiz_id': 'quiz-1',
      'status': 'in_progress',
      'blueprint_version': 3,
      'ordering_algorithm': 'backend-authoritative',
      'started_at': '2026-08-20T10:10:00Z',
      'completed_at': null,
      'archived_at': null,
      'questions': [
        {
          'attempt_question_id': 'attempt-question-2',
          'position': 2,
          'type': 'single_choice',
          'prompt': {'en': 'Server-order question one', 'ar': 'السؤال الأول بترتيب الخادم', 'fr': 'Question serveur un'},
          'response_contract': {
            'kind': 'single_choice',
            'options': [
              {'id': 'option-b', 'label': {'en': 'Option B', 'ar': 'الخيار ب', 'fr': 'Option B'}},
              {'id': 'option-a', 'label': {'en': 'Option A', 'ar': 'الخيار أ', 'fr': 'Option A'}},
            ],
          },
          'current_answer': null,
        },
        {
          'attempt_question_id': 'attempt-question-1',
          'position': 1,
          'type': 'short_text',
          'prompt': {'en': 'Server-order question two', 'ar': 'السؤال الثاني بترتيب الخادم', 'fr': 'Question serveur deux'},
          'response_contract': {'kind': 'short_text', 'max_length': 80},
          'current_answer': null,
        },
        {
          'attempt_question_id': 'attempt-question-3',
          'position': 3,
          'type': 'multiple_choice',
          'prompt': {'en': 'Server multiple-choice question'},
          'response_contract': {
            'kind': 'multiple_choice',
            'options': [
              {'id': 'option-a', 'label': {'en': 'Choice A'}},
              {'id': 'option-b', 'label': {'en': 'Choice B'}},
              {'id': 'option-c', 'label': {'en': 'Choice C'}},
            ],
          },
          'current_answer': null,
        },
      ],
    })
    ..progress = [
      ProgressSnapshot.fromJson({
        'academic_context_id': 'context-1',
        'curriculum_node_id': 'node-1',
        'mastery': 0.62,
        'source_version': 4,
        'calculated_at': '2026-08-20T10:20:00Z',
      }),
    ];
  return controller;
}

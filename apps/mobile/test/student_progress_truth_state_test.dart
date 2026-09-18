import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  testWidgets('online empty progress remains authoritative Backend empty state', (tester) async {
    final controller = _progressController(MobileViewStatus.ready);

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(
      find.text('Complete learning activity to receive backend-calculated progress.'),
      findsOneWidget,
    );
    expect(
      find.textContaining('Progress has not been loaded from the backend'),
      findsNothing,
    );
  });

  testWidgets('offline progress without fetched snapshot is truthful and retryable', (tester) async {
    final controller = _progressController(MobileViewStatus.offline);

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );

    expect(
      find.textContaining('Progress has not been loaded from the backend'),
      findsOneWidget,
    );
    expect(
      find.text('Complete learning activity to receive backend-calculated progress.'),
      findsNothing,
    );
    expect(find.text('Retry'), findsOneWidget);
    expect(find.byIcon(Icons.cloud_off_outlined), findsWidgets);
  });
}

MobileLearningController _progressController(MobileViewStatus status) {
  final controller = MobileLearningController(
    gateway: const UnconfiguredLearningGateway(),
    config: MobileBootstrapConfig(
      apiBaseUrl: Uri.parse('https://example.invalid/api/v1/'),
    ),
  );

  controller
    ..status = status
    ..section = StudentSection.progress
    ..academicContext = AcademicContext.fromJson({
      'state': 'active',
      'context_id': 'context-1',
      'academic_track_id': 'track-1',
      'year_level': 'Year 7',
      'activated_at': '2026-08-20T10:00:00Z',
    })
    ..progress = const [];

  return controller;
}

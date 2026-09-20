import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/main.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/mobile_learning_controller.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/student_preferences.dart';

void main() {
  test('Flutter restores a persisted text-size preference before learning bootstrap', () async {
    final store = MemoryStudentPreferenceStore(
      initialTextScale: StudentTextScalePreference.extraLarge,
    );
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: const MobileBootstrapConfig(apiBaseUrl: null),
      studentPreferenceStore: store,
    );

    await controller.initialize();

    expect(
      controller.textScalePreference,
      StudentTextScalePreference.extraLarge,
    );
    expect(controller.textScaleFactor, 1.5);
    expect(controller.status, MobileViewStatus.permission);
  });

  test('Flutter exposes Small / Normal / Large / Extra Large text scales', () {
    expect(StudentTextScalePreference.small.factor, 0.875);
    expect(StudentTextScalePreference.normal.factor, 1.0);
    expect(StudentTextScalePreference.large.factor, 1.25);
    expect(StudentTextScalePreference.extraLarge.factor, 1.5);
  });

  test('Flutter text-size choice survives controller recreation', () async {
    final store = MemoryStudentPreferenceStore();
    final first = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: const MobileBootstrapConfig(apiBaseUrl: null),
      studentPreferenceStore: store,
    );

    await first.setTextScalePreference(StudentTextScalePreference.large);

    final reopened = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: const MobileBootstrapConfig(apiBaseUrl: null),
      studentPreferenceStore: store,
    );
    await reopened.initialize();

    expect(reopened.textScalePreference, StudentTextScalePreference.large);
    expect(reopened.textScaleFactor, 1.25);
  });

  testWidgets('Flutter Student shell applies the selected persisted text scale', (tester) async {
    final controller = MobileLearningController(
      gateway: const UnconfiguredLearningGateway(),
      config: const MobileBootstrapConfig(apiBaseUrl: null),
    )
      ..status = MobileViewStatus.ready
      ..academicContext = AcademicContext.fromJson({
        'state': 'active',
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'year_level': 'Year 7',
        'activated_at': '2026-09-20T10:00:00Z',
      });

    await controller.setTextScalePreference(
      StudentTextScalePreference.extraLarge,
    );

    await tester.pumpWidget(
      ModrikApp(controller: controller, autoInitialize: false),
    );
    await tester.pump();

    final mediaQueries = tester
        .widgetList<MediaQuery>(find.byType(MediaQuery))
        .map((widget) => widget.data.textScaler.scale(16))
        .toList();

    expect(
      mediaQueries.any((scaled) => (scaled - 24).abs() < 0.01),
      isTrue,
    );
    expect(find.text('150%'), findsOneWidget);
  });

  test('text-size preference storage parser fails closed on unknown values', () {
    expect(
      StudentTextScalePreference.fromStorage('unsupported'),
      isNull,
    );
    expect(
      StudentTextScalePreference.fromStorage('large'),
      StudentTextScalePreference.large,
    );
    expect(
      StudentTextScalePreference.fromStorage('largest'),
      StudentTextScalePreference.extraLarge,
    );
    expect(
      StudentTextScalePreference.fromStorage('small'),
      StudentTextScalePreference.small,
    );
  });
}

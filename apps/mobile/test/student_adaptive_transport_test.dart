import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';

void main() {
  test('adaptive study uses authenticated Backend-owned scope only', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      expect(request.method, 'GET');
      expect(request.uri.path, '/api/v1/adaptive-study');
      expect(request.uri.query, isEmpty);
      expect(
        request.headers.value(HttpHeaders.authorizationHeader),
        'Bearer adaptive-session',
      );

      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'state': 'active',
          'features': {
            'daily_plan': {'state': 'enabled', 'effective': true},
            'mistake_notebook': {'state': 'enabled', 'effective': true},
          },
          'today_mission': {
            'status': 'ready',
            'selected': [
              {
                'skill_id': 'skill-1',
                'skill_reference': 'SKILL:1',
                'skill_title': {'en': 'Fractions'},
                'subject': {
                  'id': 'subject-1',
                  'reference': 'SUBJECT:MATH',
                  'title': {'en': 'Mathematics'},
                },
                'assessment': {
                  'id': 'quiz-1',
                  'kind': 'practice',
                  'title': {'en': 'Fractions practice'},
                  'available_question_count': 3,
                },
              }
            ],
          },
          'needs_practice': {'status': 'empty', 'items': []},
          'mistakes': {'status': 'empty', 'items': []},
        },
        'meta': {'request_id': 'req-adaptive-mobile'},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        bearerToken: 'adaptive-session',
      );

      final snapshot = await gateway.adaptiveStudy();

      expect(snapshot.isActive, isTrue);
      expect(snapshot.todayMission.status, 'ready');
      expect(snapshot.todayMission.items.single.skillId, 'skill-1');
      expect(snapshot.todayMission.items.single.assessment.id, 'quiz-1');
      expect(snapshot.todayMission.items.single.assessment.isAvailable, isTrue);
    } finally {
      await server.close(force: true);
    }
  });
}

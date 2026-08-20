import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/learning_gateway.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  test('catalogue forwards secure bearer and preserves backend order and labels', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    server.listen((request) async {
      expect(request.method, 'GET');
      expect(request.uri.path, '/api/v1/academic-tracks');
      expect(
        request.headers.value(HttpHeaders.authorizationHeader),
        'Bearer secure-production-session',
      );
      request.response.headers.contentType = ContentType.json;
      request.response.write(jsonEncode({
        'data': {
          'tracks': [
            {
              'id': '01J000000000000000000000B2',
              'labels': {
                'ar': 'المسار الثاني',
                'en': 'Second track',
                'fr': 'Deuxième parcours',
              },
            },
            {
              'id': '01J000000000000000000000A1',
              'labels': {
                'ar': 'المسار الأول',
                'en': 'First track',
                'fr': 'Premier parcours',
              },
            },
          ],
        },
        'meta': {'request_id': 'req-mobile-catalogue'},
      }));
      await request.response.close();
    });

    try {
      final gateway = HttpLearningGateway(
        baseUrl: Uri.parse(
          'http://${server.address.address}:${server.port}/api/v1/',
        ),
        bearerToken: 'secure-production-session',
      );
      final tracks = await gateway.academicTracks();

      expect(
        tracks.map((track) => track.id),
        ['01J000000000000000000000B2', '01J000000000000000000000A1'],
      );
      expect(tracks.first.label(ModrikLocale.ar), 'المسار الثاني');
      expect(tracks.first.label(ModrikLocale.en), 'Second track');
      expect(tracks.first.label(ModrikLocale.fr), 'Deuxième parcours');
    } finally {
      await server.close(force: true);
    }
  });
}

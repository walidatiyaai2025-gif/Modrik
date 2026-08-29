import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/academic_track_catalogue.dart';
import 'package:modrik_mobile/src/models.dart';

void main() {
  test('academic track parser preserves Backend localized year labels and response order', () {
    final payload = [
      {
        'id': '01J000000000000000000000B1',
        'year': {'key': 'YEAR:7', 'label': 'Curated Year Seven'},
        'labels': {
          'ar': 'علوم 7',
          'en': 'Science 7',
          'fr': 'Sciences 7',
        },
      },
      {
        'id': '01J000000000000000000000A1',
        'year': {'key': 'YEAR:6', 'label': 'Curated Year Six'},
        'labels': {
          'ar': 'علوم 6',
          'en': 'Science 6',
          'fr': 'Sciences 6',
        },
      },
    ];

    final tracks = payload.map(AcademicTrack.fromJson).toList(growable: false);

    expect(tracks.map((track) => track.id).toList(), [
      '01J000000000000000000000B1',
      '01J000000000000000000000A1',
    ]);
    expect(tracks.first.year?.key, 'YEAR:7');
    expect(tracks.first.year?.label, 'Curated Year Seven');
    expect(tracks.last.year?.label, 'Curated Year Six');
    expect(tracks.first.label(ModrikLocale.en), 'Science 7');
  });
}

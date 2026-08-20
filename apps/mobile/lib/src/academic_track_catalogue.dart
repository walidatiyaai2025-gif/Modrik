import 'dart:collection';

import 'models.dart';

class AcademicTrack {
  AcademicTrack({required this.id, required Map<ModrikLocale, String> labels})
      : labels = UnmodifiableMapView(Map<ModrikLocale, String>.from(labels));

  factory AcademicTrack.fromJson(Map<String, dynamic> json) => AcademicTrack(
        id: json['id'] as String,
        labels: localizedTextFromJson(json['labels']),
      );

  final String id;
  final Map<ModrikLocale, String> labels;

  String label(ModrikLocale locale) => localize(labels, locale);
}

abstract interface class AcademicTrackCatalogueGateway {
  Future<List<AcademicTrack>> academicTracks();
}

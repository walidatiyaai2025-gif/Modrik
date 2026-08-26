import 'dart:collection';

import 'models.dart';

class AcademicYear {
  const AcademicYear({required this.key, required this.label});

  factory AcademicYear.fromJson(Map<String, dynamic> json) => AcademicYear(
        key: json['key'] as String,
        label: json['label'] as String,
      );

  final String key;
  final String label;
}

class AcademicTrack {
  AcademicTrack({
    required this.id,
    this.year,
    required Map<ModrikLocale, String> labels,
  }) : labels = UnmodifiableMapView(Map<ModrikLocale, String>.from(labels));

  factory AcademicTrack.fromJson(Map<String, dynamic> json) {
    final yearJson = json['year'];
    return AcademicTrack(
      id: json['id'] as String,
      year: yearJson is Map
          ? AcademicYear.fromJson(Map<String, dynamic>.from(yearJson))
          : null,
      labels: localizedTextFromJson(json['labels']),
    );
  }

  final String id;
  final AcademicYear? year;
  final Map<ModrikLocale, String> labels;

  String label(ModrikLocale locale) => localize(labels, locale);
}

abstract interface class AcademicTrackCatalogueGateway {
  Future<List<AcademicTrack>> academicTracks();
}

import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/models.dart';
import 'package:modrik_mobile/src/student_content_catalogue.dart';

void main() {
  test('published catalogue preserves subject unit topic lesson and assessments', () {
    final catalogue = ContentCatalogue.fromJson({
      'state': 'active',
      'context': {
        'context_id': 'context-1',
        'academic_track_id': 'track-1',
        'track_reference': 'TRACK:KUWAIT-GRADE-6-NATIONAL-CURRICULUM:38ECB0A6',
        'year_level': 'YEAR:ALSF-ALSADS:1283F4DE',
        'track_title': {
          'ar': 'المنهج الوطني الكويتي – الصف السادس',
          'en': 'Kuwait Grade 6 National Curriculum',
          'fr': 'Programme national du Koweït – 6e année',
        },
      },
      'subjects': [
        {
          'id': 'subject-id',
          'reference': 'SUBJECT:ALLGH-ALAARBY:5412A28C',
          'type': 'subject',
          'title': {'ar': 'اللغة العربية', 'en': 'Arabic'},
          'lessons': [],
          'assessments': [],
          'children': [
            {
              'id': 'unit-id',
              'reference': 'UNIT:AR6-T1-U1',
              'type': 'unit',
              'title': {'ar': 'الوحدة الأولى', 'en': 'Unit 1'},
              'lessons': [],
              'assessments': [
                {
                  'id': 'mock-1',
                  'kind': 'mock_exam',
                  'blueprint_version': 1,
                  'title': {'ar': 'اختبار تجريبي', 'en': 'Mock exam'},
                },
              ],
              'children': [
                {
                  'id': 'topic-id',
                  'reference': 'TOPIC:AR6-T1-U1-T1',
                  'type': 'topic',
                  'title': {'ar': 'آيات من سورة الأنبياء', 'en': 'Verses'},
                  'lessons': [
                    {
                      'id': 'lesson-1',
                      'slug': 'u1-t1-l1',
                      'content_version': 1,
                      'title': {'ar': 'الدرس الأول', 'en': 'Lesson 1'},
                      'published_at': '2026-08-27T12:00:00Z',
                    },
                  ],
                  'assessments': [
                    {
                      'id': 'practice-1',
                      'kind': 'practice',
                      'blueprint_version': 1,
                      'title': {'ar': 'تدريب', 'en': 'Practice'},
                    },
                  ],
                  'children': [],
                },
              ],
            },
          ],
        },
      ],
      'counts': {'subjects': 1, 'lessons': 56, 'assessments': 6},
    });

    expect(catalogue.isActive, isTrue);
    expect(catalogue.context?.academicTrackId, 'track-1');
    expect(catalogue.counts.lessons, 56);
    expect(catalogue.subjects, hasLength(1));
    expect(catalogue.subjects.single.reference,
        'SUBJECT:ALLGH-ALAARBY:5412A28C');

    final unit = catalogue.subjects.single.children.single;
    final topic = unit.children.single;
    expect(unit.type, 'unit');
    expect(unit.assessments.single.kind, 'mock_exam');
    expect(topic.type, 'topic');
    expect(topic.lessons.single.id, 'lesson-1');
    expect(topic.assessments.single.kind, 'practice');
  });

  test('published lesson safely accepts nullable practice quiz', () {
    final lesson = publishedLessonFromJson({
      'id': 'lesson-without-direct-practice',
      'curriculum_node_id': 'topic-id',
      'content_version': 1,
      'title': {'ar': 'درس منشور', 'en': 'Published lesson'},
      'practice_quiz_id': null,
      'blocks': [
        {
          'id': 'block-1',
          'position': 1,
          'type': 'rich_text',
          'content': {'ar': 'محتوى الدرس', 'en': 'Lesson content'},
        },
      ],
    });

    expect(lesson.practiceQuizId, isEmpty);
    expect(localize(lesson.title, ModrikLocale.ar), 'درس منشور');
    expect(lesson.blocks, hasLength(1));
  });

  test('onboarding catalogue remains fail-closed with no subjects', () {
    final catalogue = ContentCatalogue.fromJson({
      'state': 'onboarding_required',
      'subjects': [],
      'counts': {'subjects': 0, 'lessons': 0, 'assessments': 0},
    });

    expect(catalogue.isActive, isFalse);
    expect(catalogue.context, isNull);
    expect(catalogue.subjects, isEmpty);
  });
}

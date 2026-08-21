import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/runtime_correlation.dart';

void main() {
  test('generated client correlation remains a valid UUID-shaped contract value', () {
    final correlationId = createDiagnosticCorrelationId();

    expect(validDiagnosticCorrelationId(correlationId), correlationId);
    expect(correlationId, matches(RegExp(r'^[0-9a-f-]{36}$')));
  });

  test('accepts canonical Backend safe correlation envelope', () {
    const backendCorrelation = 'gateway.req:pilot-ABC_1234';

    expect(
      validDiagnosticCorrelationId(backendCorrelation),
      backendCorrelation,
    );
  });

  test('rejects correlation values outside canonical safe envelope', () {
    expect(validDiagnosticCorrelationId('short'), isNull);
    expect(validDiagnosticCorrelationId('-leading-punctuation-id'), isNull);
    expect(validDiagnosticCorrelationId('contains space 123456'), isNull);
    expect(validDiagnosticCorrelationId('contains/slash/123456'), isNull);
    expect(validDiagnosticCorrelationId('a' * 97), isNull);
    expect(validDiagnosticCorrelationId('valid-looking-id-123\n'), isNull);
  });
}

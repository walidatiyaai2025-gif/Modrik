import 'package:flutter_test/flutter_test.dart';
import 'package:modrik_mobile/src/runtime_correlation.dart';

void main() {
  test('generated client correlation remains a valid UUID-shaped contract value', () {
    final correlationId = createDiagnosticCorrelationId();

    expect(validTransportCorrelationId(correlationId), correlationId);
    expect(validDiagnosticCorrelationId(correlationId), correlationId);
    expect(correlationId, matches(RegExp(r'^[0-9a-f-]{36}$')));
  });

  test('accepts canonical Backend safe correlation envelope', () {
    const backendCorrelation = 'gateway.req:pilot-ABC_1234';

    expect(
      validTransportCorrelationId(backendCorrelation),
      backendCorrelation,
    );
    expect(
      validDiagnosticCorrelationId(backendCorrelation),
      backendCorrelation,
    );
  });

  test('keeps broad transport grammar but rejects secret-shaped diagnostic IDs', () {
    const secretShaped = 'SENTINEL-password-value';

    expect(validTransportCorrelationId(secretShaped), secretShaped);
    expect(validDiagnosticCorrelationId(secretShaped), isNull);
  });

  test('rejects correlation values outside canonical safe envelope', () {
    expect(validTransportCorrelationId('short'), isNull);
    expect(validTransportCorrelationId('-leading-punctuation-id'), isNull);
    expect(validTransportCorrelationId('contains space 123456'), isNull);
    expect(validTransportCorrelationId('contains/slash/123456'), isNull);
    expect(validTransportCorrelationId(List.filled(97, 'a').join()), isNull);
    expect(validTransportCorrelationId('valid-looking-id-123\n'), isNull);
  });
}

import 'dart:math';

const diagnosticCorrelationHeader = 'X-Correlation-ID';
const diagnosticFallbackCorrelationHeader = 'X-Request-ID';

final RegExp _uuidPattern = RegExp(
  r'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
  caseSensitive: false,
);
final RegExp _ulidPattern = RegExp(r'^[0-9A-HJKMNP-TV-Z]{26}$');

String? validDiagnosticCorrelationId(String? value) {
  if (value == null) return null;
  final candidate = value.trim();
  if (_uuidPattern.hasMatch(candidate) || _ulidPattern.hasMatch(candidate)) {
    return candidate;
  }
  return null;
}

String createDiagnosticCorrelationId() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  final hex = bytes
      .map((value) => value.toRadixString(16).padLeft(2, '0'))
      .join();
  return '${hex.substring(0, 8)}-'
      '${hex.substring(8, 12)}-'
      '${hex.substring(12, 16)}-'
      '${hex.substring(16, 20)}-'
      '${hex.substring(20)}';
}

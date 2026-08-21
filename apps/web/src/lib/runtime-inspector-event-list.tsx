import type { RuntimeDiagnosticEvent } from "./runtime-diagnostics";

type TimelineLabels = {
  timeline: string;
  empty: string;
  status: string;
  support: string;
  copyCorrelation: string;
};

type Props = {
  events: RuntimeDiagnosticEvent[];
  labels: TimelineLabels;
  timelineClassName?: string;
  emptyClassName?: string;
  eventHeadingClassName?: string;
  correlationRowClassName?: string;
  copyCorrelationClassName?: string;
  onCopyCorrelation?: (correlationId: string) => void;
};

function eventKey(event: RuntimeDiagnosticEvent, index: number) {
  return `${event.timestamp}:${event.correlationId ?? "none"}:${event.operation}:${index}`;
}

export function RuntimeInspectorEventList({
  events,
  labels,
  timelineClassName,
  emptyClassName,
  eventHeadingClassName,
  correlationRowClassName,
  copyCorrelationClassName,
  onCopyCorrelation,
}: Props) {
  return (
    <section className={timelineClassName} aria-labelledby="runtime-timeline-title" aria-live="polite">
      <h3 id="runtime-timeline-title">{labels.timeline}</h3>
      {events.length === 0 ? <p className={emptyClassName}>{labels.empty}</p> : null}
      <ol>
        {events.map((event, index) => (
          <li key={eventKey(event, index)}>
            <div className={eventHeadingClassName}>
              <strong>{event.operation}</strong>
              <span>
                {event.severity} · {event.category}
              </span>
            </div>
            <div className={correlationRowClassName}>
              <code>{event.correlationId ?? "—"}</code>
              {event.correlationId ? (
                <button
                  type="button"
                  className={copyCorrelationClassName}
                  onClick={() => onCopyCorrelation?.(event.correlationId ?? "")}
                >
                  {labels.copyCorrelation}
                </button>
              ) : null}
            </div>
            <dl>
              <div>
                <dt>{labels.status}</dt>
                <dd>{event.status ?? event.resultClass ?? "—"}</dd>
              </div>
              <div>
                <dt>{labels.support}</dt>
                <dd>{event.supportReference ?? "—"}</dd>
              </div>
            </dl>
          </li>
        ))}
      </ol>
    </section>
  );
}

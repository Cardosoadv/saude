import './setup-globals';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { debounce } from '../src/services/api';

describe('debounce utility function', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('should delay the execution of the target function', () => {
    const callback = vi.fn();
    const debounced = debounce(callback, 200);

    debounced();

    // Not called immediately
    expect(callback).not.toHaveBeenCalled();

    // Fast-forward time to just before delay
    vi.advanceTimersByTime(199);
    expect(callback).not.toHaveBeenCalled();

    // Fast-forward to delay completion
    vi.advanceTimersByTime(1);
    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('should only call the target function once after multiple rapid invocations', () => {
    const callback = vi.fn();
    const debounced = debounce(callback, 200);

    debounced();
    debounced();
    debounced();

    expect(callback).not.toHaveBeenCalled();

    vi.advanceTimersByTime(200);
    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('should pass the latest arguments to the target function', () => {
    const callback = vi.fn();
    const debounced = debounce(callback, 100);

    debounced('first', 1);
    debounced('second', 2);
    debounced('third', 3);

    expect(callback).not.toHaveBeenCalled();

    vi.advanceTimersByTime(100);
    expect(callback).toHaveBeenCalledWith('third', 3);
    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('should reset the timer on subsequent calls before the delay expires', () => {
    const callback = vi.fn();
    const debounced = debounce(callback, 150);

    debounced();

    // Wait 100ms (not yet expired)
    vi.advanceTimersByTime(100);
    expect(callback).not.toHaveBeenCalled();

    // Trigger again: timer should be reset and start a new 150ms delay
    debounced();

    // Wait another 100ms (total 200ms from start, but only 100ms from 2nd trigger)
    vi.advanceTimersByTime(100);
    expect(callback).not.toHaveBeenCalled();

    // Wait another 50ms (total 250ms from start, and 150ms from 2nd trigger)
    vi.advanceTimersByTime(50);
    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('should maintain distinct timer instances for separate debounced wrappers', () => {
    const callback1 = vi.fn();
    const callback2 = vi.fn();

    const debounced1 = debounce(callback1, 100);
    const debounced2 = debounce(callback2, 200);

    debounced1();
    debounced2();

    vi.advanceTimersByTime(100);
    expect(callback1).toHaveBeenCalledTimes(1);
    expect(callback2).not.toHaveBeenCalled();

    vi.advanceTimersByTime(100);
    expect(callback2).toHaveBeenCalledTimes(1);
  });
});

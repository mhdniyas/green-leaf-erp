# Karpathy Coding Guidelines

Supporting behavioral rule set for deliberate and surgical code execution.

## Core Principles

1. **Think Before Coding**: Trace exact execution paths and state before making edits. Do not make assumptions.
2. **Simplicity First**: Write the smallest, cleanest solution possible. Avoid over-engineering, unnecessary wrappers, or speculation.
3. **Surgical Changes**: Keep edits narrowly scoped to the requested goal. Do not modify unrelated lines or refactor adjacent code.
4. **Goal-Driven Verification**: Test and verify the exact result programmatically or via runtime logs. Once confirmed, stop.

## Operating Hierarchy

1. **Graphify**: Navigate and map exact code structures, dependencies, and relationships.
2. **Caveman**: Communicate tersely and investigate with minimal token overhead.
3. **Karpathy Rules**: Execute smallest correct edit, verify result, and finish.

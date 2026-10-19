---
ijfw_version: 1.3.2
ijfw_schema: 1
type: software
primary_type: software
secondary_types: []
confidence: 0.907
detected_at: 2026-10-18T23:01:29.056Z
signals:
  - kind: manifest
    weight: 0.9
    manifests: [composer.json]
  - kind: file_extension_ratio
    weight: 0.7
    domain: software
    ratio: 1
    count: 62
---
# AGENTS.md

This file follows the open AGENTS.md spec (https://agents.md/) and is the
canonical agent-instructions surface for this project. Platform-specific
files (CLAUDE.md, GEMINI.md, WAYLAND.md, codex/AGENTS.md, .cursorrules,
.windsurfrules, copilot-instructions.md) are thin adapters that point here.

Five IJFW-managed regions live in this file. Content outside the markers is
yours -- IJFW will never touch it.

| Region | Purpose |
|---|---|
| MEMORY | Project memory recalled from `.ijfw/memory/` |
| ROUTING | Platform skill-routing rules |
| AGENTS | Registered agent roster |
| BLACKBOARD | Multi-CLI orchestration scratchpad (Pillar B) |
| DISCIPLINE | Per-domain discipline rules (code \| narrative \| business \| design \| research) |

<!-- IJFW-MEMORY-START -->
Project memory at .ijfw/memory/. Call `ijfw_memory_prelude` for full context.
<!-- IJFW-MEMORY-END -->

<!-- IJFW-ROUTING-START -->
<!-- IJFW-ROUTING-END -->

<!-- IJFW-AGENTS-START -->
No project agents yet. Run `ijfw team` to set them up.
<!-- IJFW-AGENTS-END -->

<!-- IJFW-BLACKBOARD-START -->
<!-- Reserved for Pillar B multi-CLI orchestration. Empty in alpha. -->
<!-- IJFW-BLACKBOARD-END -->

<!-- IJFW-DISCIPLINE-START -->
<!-- IJFW-DISCIPLINE-END -->

## Package contributor guidance

Read [Contributing](docs/development/contributing.md), [Testing](docs/development/testing.md) and the [agent workflow](docs/development/agent-workflow.md) when changing this repository. The consumer docs index is [docs/README.md](docs/README.md). Keep public API examples, TypeScript payloads and shipped Boost resources consistent with implementation changes.

The guidance under `resources/boost` targets consuming applications. See [Boost setup](docs/boost.md) for discovery conventions; do not install application agent configuration into this repository as a side effect of validating those resources.

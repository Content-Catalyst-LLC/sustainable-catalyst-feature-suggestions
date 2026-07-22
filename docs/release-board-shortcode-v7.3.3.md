# Release Console v7.3.3

## Release Console Reliability and Presentation Repair

`[sc_release_board]` remains the canonical shortcode. The default terminal layout retains the five-screen, seven-second Release Console introduced in v7.3.2 while repairing production presentation and interaction edge cases.

## Reliability behavior

- All enhanced screens occupy the same CSS grid cell. The grid reserves the tallest screen, preventing footer movement and clipping during rotation.
- Controls are hidden until JavaScript initializes successfully. Without JavaScript, all release groups remain visible in their governed order.
- Every console instance receives unique heading, screen-region, announcer, and control relationships, including cached shortcode output.
- A duplicate-initialization guard prevents multiple timers on the same console. A mutation observer initializes consoles inserted after page load.
- Automatic rotation pauses on hover, keyboard focus, hidden browser tabs, and reduced-motion preference changes.

## Keyboard operation

Focus the release-screen region and use:

- Left Arrow: previous screen
- Right Arrow: next screen
- Home: Foundation
- End: Commercial Release
- Space: pause or play

Previous, Pause/Play, and Next buttons remain standard keyboard-accessible buttons. Manual navigation is announced politely; automatic rotation does not trigger live-region narration.

## Compatibility

```text
[sc_release_board]
[sc_release_board interval="10"]
[sc_release_board rotate="no" layout="terminal"]
[sc_release_board layout="blackboard"]
[sc_release_board layout="compact"]
[sc_release_board layout="directory" context="directory"]
```

Blackboard, compact, directory, and static terminal output remain compatible. Product names and versions are console labels, not links. Release and Support remain the only links and appear only in the fixed footer.

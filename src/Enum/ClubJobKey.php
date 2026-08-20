<?php

namespace App\Enum;

/**
 * One case per background job tracked via `ClubJob`. To add a new background job:
 *   1. Add a case here.
 *   2. Create the `Message`/`MessageHandler` pair (see src/Message, src/MessageHandler).
 *   3. Route the message class to a transport in config/packages/messenger.yaml — this step
 *      can't be automated away, Symfony Messenger routing is explicit by design.
 *   4. Call `ClubService::startJob($club, ClubJobKey::your_key, $total)` from your producer, and
 *      have your message's `getJobKey()` return `ClubJobKey::your_key` so the worker's
 *      `MessengerSubscriber` can decrement progress automatically.
 * See CONTRIBUTING.md "Adding a new background job" for the full walkthrough.
 */
enum ClubJobKey: string {
  case IMPORT_CERBERE = 'import_cerbere';
  case IMPORT_ITAC = 'import_itac';
  case IMPORT_ITAC_SECONDARY = 'import_itac_secondary';

  case MEMBER_CONTROL_SYNC = 'member_control_sync';
}

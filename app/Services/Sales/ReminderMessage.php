<?php

namespace App\Services\Sales;

/**
 * Writes the overdue reminder. The wording gets firmer the later the invoice is, but stays polite and factual.
 * Change the text here to change every reminder.
 */
class ReminderMessage
{
    public const FRIENDLY_UNTIL = 14;   // days overdue
    public const FOLLOW_UP_UNTIL = 30;

    public static function tone(int $daysOverdue): string
    {
        return $daysOverdue <= self::FRIENDLY_UNTIL ? 'friendly' : ($daysOverdue <= self::FOLLOW_UP_UNTIL ? 'follow_up' : 'final');
    }

    /**
     * @param array{customer:string, number:string, amount:float, due:string, days:int, seller:string, bank:?array} $d
     * @return array{tone:string, subject:string, body:string}
     */
    public static function build(array $d): array
    {
        $tone = self::tone($d['days']);
        $amount = '$' . number_format($d['amount'], 2);
        $days = $d['days'] . ' day' . ($d['days'] === 1 ? '' : 's');
        $due = date('j M Y', strtotime($d['due']));

        $subject = match ($tone) {
            'friendly' => "Reminder: invoice {$d['number']} ({$amount})",
            'follow_up' => "Overdue invoice {$d['number']} ({$amount})",
            'final' => "Final reminder: overdue invoice {$d['number']} ({$amount})",
        };

        $opening = match ($tone) {
            'friendly' => "Just a friendly reminder that invoice {$d['number']} for {$amount} was due on {$due} and is now {$days} overdue.",
            'follow_up' => "Invoice {$d['number']} for {$amount} was due on {$due} and is now {$days} overdue. We have not yet received payment.",
            'final' => "This is a final reminder that invoice {$d['number']} for {$amount} was due on {$due} and is now {$days} overdue. Please arrange payment as soon as you can, or contact us today if there is a problem.",
        };

        $pay = '';
        if (!empty($d['bank'])) {
            $pay = "\n\nYou can pay by bank transfer:\nAccount name: {$d['bank']['account_name']}\nBSB: {$d['bank']['bsb']}\nAccount number: {$d['bank']['account_number']}\nReference: {$d['number']}";
        }

        $body = "Hi {$d['customer']},\n\n{$opening}{$pay}\n\n"
            . "If you have already paid, thank you, and please ignore this message. If anything about the invoice is not right, just reply and let us know.\n\n"
            . "Kind regards,\n{$d['seller']}";

        return ['tone' => $tone, 'subject' => $subject, 'body' => $body];
    }
}

#
# Home Automation Hub
# Helper Functions
#

"""Helper Functions."""

import sys
import logging
import socket
import smtplib
from email.mime.text import MIMEText
from psycopg2 import ProgrammingError
import hub_connect

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def get_hostname():

    """Get host name."""

    return socket.gethostname()


def get_ip_address():

    """Get host ip."""

    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.connect(("8.8.8.8", 80))
    return sock.getsockname()[0]


def get_remote_url(tgt_url_tmplt, subnet_host):

    """Get remote url."""

    this_host = get_ip_address()
    sep = '.'
    HUB_LOGGER.info("host ip: %s", this_host)
    this_host_parts = this_host.split(sep)
    this_host_parts[3] = subnet_host
    tgt_host = sep.join(this_host_parts)
    HUB_LOGGER.info("remote target host ip: %s", tgt_host)

    # compute target url
    tgt_url = tgt_url_tmplt.format(tgt_host,)
    HUB_LOGGER.info("remote target url: %s", tgt_url)
    return tgt_url


def send_text(body, phone_num):

    """Send text."""
    # e.g. phone_num@txtlocal.co.uk
    text_provider = get_user_setting('TextProvider')
    if text_provider is not None:
        send_email(body, '', text_provider.format(phone_num))
    else:
        HUB_LOGGER.info("No Text Message Provider configured")


def send_email(
    txt_body,
    subject,
    recipient
):

    """Send email."""

    try:

        mail_server = get_user_setting('MailServer')

        if mail_server is not None and recipient is not None:

            # descriptive field for mail source
            msg_from = get_user_setting('EmailFrom')

            # some mail systems may need a valid sender email address
            sender = get_user_setting('EmailSender')

            msg = MIMEText(txt_body)
            msg['Subject'] = subject
            msg['From'] = msg_from
            msg['To'] = recipient

            # Send the message via the SMTP server, but don't include the
            # envelope header.
            HUB_LOGGER.info("sending email to: %s", recipient)
            smtp = smtplib.SMTP(mail_server)
            smtp.sendmail(sender, [recipient], msg.as_string())
            smtp.quit()

    except smtplib.SMTPException:
        HUB_LOGGER.error("Unable to Send Email")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)


def get_user_setting(name):

    """Get user setting."""

    try:

        # Establish database Connection
        conn = hub_connect.get_connection()

        # Create cursor - needed for any database operation
        cur = hub_connect.get_cursor(conn)
        select_sql = ('SELECT "Value" FROM "UserSetting"'
                      'WHERE "Name" = %s;')
        cur.execute(select_sql, (name,))
        if cur.rowcount > 0:
            # Read first result value column
            value = cur.fetchone()[0]
        else:
            value = None

        return value

    except ProgrammingError:
        HUB_LOGGER.error("Unable to get User Setting")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)

#
# Home Automation Hub
# Helper Script to evaluate expressions
#

"""Evaluate Helper Function."""

import sys
import logging
from math import *
import random
import time
import datetime
from datetime import timedelta

# --- Global Variables ---
HUB_LOGGER = logging.getLogger('HubLogger')


def evaluate(expression, cur_vals):

    """Evaluate expression."""

    HUB_LOGGER.info("In Evaluation Helper Function '%s'", expression)

    # parse the expression and insert real values
    result = None
    try:
        expr = expression.strip()
        parts = expr.split(' ')
        for part in parts:
            HUB_LOGGER.info("part: %s", part)
            if part.startswith('S'):
                HUB_LOGGER.info("Sensor Number: %s %s",
                                part[1:],
                                cur_vals[int(part[1:])])
                expr = expr.replace(part,
                                    str(cur_vals[int(part[1:])]))
        HUB_LOGGER.info("expr: %s", expr)

        # now evaluate the expression
        result = round(eval(expr), 2)
        HUB_LOGGER.info("Evaluated '%s' as '%s' => %s",
                        expression,
                        expr,
                        result)
    except Exception:
        HUB_LOGGER.error("Unable to parse expression")
        etype = sys.exc_info()[0]
        value = sys.exc_info()[1]
        trace = sys.exc_info()[2]
        line = trace.tb_lineno
        HUB_LOGGER.error("%s %s %s", etype, value, line)

    # return result and optional timestamp
    return (result, )

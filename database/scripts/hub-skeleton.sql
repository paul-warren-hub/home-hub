--
-- PostgreSQL database dump
--

-- Dumped from database version 9.4.10
-- Dumped by pg_dump version 9.5rc1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: hub; Type: DATABASE; Schema: -; Owner: postgres
--

CREATE DATABASE hub WITH TEMPLATE = template0 ENCODING = 'UTF8' LC_COLLATE = 'en_GB.UTF-8' LC_CTYPE = 'en_GB.UTF-8';


ALTER DATABASE hub OWNER TO postgres;

\connect hub

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
--SET row_security = off;

--
-- Name: hub; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON DATABASE hub IS 'The home-hub database';


--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: StatisticalFunction; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE "StatisticalFunction" AS ENUM (
    'Maximum',
    'Minimum',
    'Average'
);


ALTER TYPE "StatisticalFunction" OWNER TO postgres;


--
-- Name: actuator_timers(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION actuator_timers() RETURNS trigger
    LANGUAGE plpgsql
    AS $$



    BEGIN

        -- Rising Edge

        IF NEW."CurrentValue" = 1 AND OLD."CurrentValue" = 0 THEN

		RAISE NOTICE 'Rising Edge';--comment

		NEW."OnForMins" := 0;

		NEW."OffForMins" := -1;

        END IF;

        -- Falling Edge

       IF NEW."CurrentValue" = 0 AND OLD."CurrentValue" = 1 THEN

		RAISE NOTICE 'Falling Edge';--comment

		NEW."OnForMins" := -1;

		NEW."OffForMins" := 0;

        END IF;

        RETURN NEW;

    END;

$$;


ALTER FUNCTION public.actuator_timers() OWNER TO postgres;

--
-- Name: chartdate(timestamp without time zone); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION chartdate(timestamp without time zone) RETURNS character
    LANGUAGE sql
    AS $_$
    SELECT to_char($1, '"Date("YYYY,') || date_part('month', $1)-1 || to_char($1, ',FMDD,FMHH24,FMMI,1)') AS result;
$_$;


ALTER FUNCTION public.chartdate(timestamp without time zone) OWNER TO postgres;

--
-- Name: chartdate(timestamp with time zone); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION chartdate(timestamp with time zone) RETURNS character
    LANGUAGE sql
    AS $_$
    SELECT to_char($1, '"Date("YYYY,') || date_part('month', $1)-1 || to_char($1, ',FMDD,FMHH24,FMMI,1)') AS result;
$_$;


ALTER FUNCTION public.chartdate(timestamp with time zone) OWNER TO postgres;

--
-- Name: condition_expressions(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION condition_expressions() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
    BEGIN
	RAISE NOTICE 'in condition_expressions';--comment
	-- Set Expression
        -- Check that sensor, operator and threshold are given
        IF (NEW."Source" IS NOT NULL AND NEW."SetThreshold" IS NOT NULL)
		AND (TG_OP = 'INSERT' OR (NEW."Source" != OLD."Source" OR NEW."SetOperator" != OLD."SetOperator" OR NEW."SetThreshold" != OLD."SetThreshold"))
	THEN
			-- Assemble Expression
			RAISE NOTICE 'condition_expressions - assembling set expression';--comment

			-- Times
			UPDATE "Condition" 

				SET "SetExpression" = format(
					'(%2$L < %3$L and %1$s >= %2$L and %1$s < %3$L) or (%2$L > %3$L and %1$s >= %2$L)', 
						NEW."Source", NEW."SetThreshold", NEW."ResetThreshold")

			WHERE "ConditionID" = NEW."ConditionID" AND NEW."Source" IN ('TOD','TOWD','TOWE');

			-- Other Simples
			UPDATE "Condition" 

				SET "SetExpression" = format('%s %s %s', NEW."Source", NEW."SetOperator", NEW."SetThreshold")

			WHERE "ConditionID" = NEW."ConditionID" AND NEW."Source" NOT IN ('TOD','TOWD','TOWE');

        END IF;

	-- ReSet Expression
        -- Check that sensor, operator and threshold are given
        IF (NEW."Source" IS NOT NULL AND NEW."ResetThreshold" IS NOT NULL)
		AND (TG_OP = 'INSERT' OR (NEW."Source" != OLD."Source" OR NEW."ResetOperator" != OLD."ResetOperator" OR NEW."ResetThreshold" != OLD."ResetThreshold"))
	THEN
			-- Assemble Expression
			RAISE NOTICE 'condition_expressions - assembling reset expression';--comment

			-- Times
			UPDATE "Condition" 
				--Use TOD for all Reset conditions
				SET "ResetExpression" = format(
					'(%1$L < %2$L and TOD >= %2$L) or (%1$L > %2$L and TOD < %1$L and TOD >= %2$L)', 
					NEW."SetThreshold", NEW."ResetThreshold")

			WHERE "ConditionID" = NEW."ConditionID" AND NEW."Source" IN ('TOD','TOWD','TOWE');

			-- Other Simples
			UPDATE "Condition" 

				SET "ResetExpression" = format('%s %s %s', NEW."Source", NEW."ResetOperator", NEW."ResetThreshold")

			WHERE "ConditionID" = NEW."ConditionID" AND NEW."Source" NOT IN ('TOD','TOWD','TOWE');

        END IF;

        RETURN NEW;
    END;
$_$;


ALTER FUNCTION public.condition_expressions() OWNER TO postgres;

--
-- Name: first_agg(anyelement, anyelement); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION first_agg(anyelement, anyelement) RETURNS anyelement
    LANGUAGE sql IMMUTABLE STRICT
    AS $_$
        SELECT $1;
$_$;


ALTER FUNCTION public.first_agg(anyelement, anyelement) OWNER TO postgres;

--
-- Name: last_agg(anyelement, anyelement); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION last_agg(anyelement, anyelement) RETURNS anyelement
    LANGUAGE sql IMMUTABLE STRICT
    AS $_$
        SELECT $2;
$_$;


ALTER FUNCTION public.last_agg(anyelement, anyelement) OWNER TO postgres;

--
-- Name: first(anyelement); Type: AGGREGATE; Schema: public; Owner: postgres
--

CREATE AGGREGATE first(anyelement) (
    SFUNC = first_agg,
    STYPE = anyelement
);


ALTER AGGREGATE public.first(anyelement) OWNER TO postgres;

--
-- Name: last(anyelement); Type: AGGREGATE; Schema: public; Owner: postgres
--

CREATE AGGREGATE last(anyelement) (
    SFUNC = last_agg,
    STYPE = anyelement
);


ALTER AGGREGATE public.last(anyelement) OWNER TO postgres;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: Action; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Action" (
    "ActionID" integer NOT NULL,
    "ActionName" character varying(64),
    "ActionDescription" character varying(255),
    "ActionFunction" character varying(255),
    "Enabled" boolean DEFAULT true,
    "EmailRecipient" character varying(128),
    "TextRecipient" character varying(128)
);


ALTER TABLE "Action" OWNER TO postgres;

--
-- Name: TABLE "Action"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Action" IS 'An action may be to switch something on, off or to issue an alarm or an alert.';


--
-- Name: COLUMN "Action"."ActionFunction"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "Action"."ActionFunction" IS 'Signature for the specific action function';


--
-- Name: Action_ActionID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Action_ActionID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Action_ActionID_seq" OWNER TO postgres;

--
-- Name: Action_ActionID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Action_ActionID_seq" OWNED BY "Action"."ActionID";


--
-- Name: Actuator; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Actuator" (
    "ActuatorEntryID" integer NOT NULL,
    "ActuatorDescription" character varying(255),
    "ActuatorTypeID" integer,
    "CurrentValue" numeric(6,2) DEFAULT 0.0,
    "ZoneID" integer,
    "ActuatorName" character varying(64),
    "IsInAuto" character(1) DEFAULT 'N'::bpchar,
    "LastUpdated" timestamp without time zone,
    "UpdatedBy" character varying(64),
    "ActuatorID" integer,
    "WebPresence" boolean,
    "Enabled" boolean,
    "ActuatorFunction" character varying(1024),
    "OnForMins" integer DEFAULT (-1),
    "OffForMins" integer DEFAULT 0
);


ALTER TABLE "Actuator" OWNER TO postgres;

--
-- Name: TABLE "Actuator"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Actuator" IS 'An Actuator is an output from the hub.';


--
-- Name: COLUMN "Actuator"."CurrentValue"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "Actuator"."CurrentValue" IS 'Actuators may be analogue or digital';


--
-- Name: COLUMN "Actuator"."ActuatorID"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "Actuator"."ActuatorID" IS 'Fixed ID of Actuator independent of entry';


--
-- Name: COLUMN "Actuator"."ActuatorFunction"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "Actuator"."ActuatorFunction" IS 'Support Function';


--
-- Name: ActuatorType; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "ActuatorType" (
    "ActuatorTypeID" integer NOT NULL,
    "ActuatorTypeName" character varying(64),
    "ActuatorTypeDescription" character varying(255)
);


ALTER TABLE "ActuatorType" OWNER TO postgres;

--
-- Name: TABLE "ActuatorType"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "ActuatorType" IS 'The ActuatorType will dictate how the actuator can be controlled.';


--
-- Name: ActuatorType_ActuatorTypeID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "ActuatorType_ActuatorTypeID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "ActuatorType_ActuatorTypeID_seq" OWNER TO postgres;

--
-- Name: ActuatorType_ActuatorTypeID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "ActuatorType_ActuatorTypeID_seq" OWNED BY "ActuatorType"."ActuatorTypeID";


--
-- Name: Actuator_ActuatorEntryID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Actuator_ActuatorEntryID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Actuator_ActuatorEntryID_seq" OWNER TO postgres;

--
-- Name: Actuator_ActuatorEntryID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Actuator_ActuatorEntryID_seq" OWNED BY "Actuator"."ActuatorEntryID";


--
-- Name: Condition_ConditionID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Condition_ConditionID_seq"
    START WITH 7
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Condition_ConditionID_seq" OWNER TO postgres;

--
-- Name: Condition; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Condition" (
    "ConditionID" integer DEFAULT nextval('"Condition_ConditionID_seq"'::regclass) NOT NULL,
    "ConditionName" character varying(64),
    "ConditionDescription" character varying(255),
    "SetOperator" character varying(2),
    "SetThreshold" character varying(64),
    "SetExpression" character varying(256),
    "ResetOperator" character varying(2),
    "ResetThreshold" character varying(64),
    "ResetExpression" character varying(256),
    "CurrentValue" boolean DEFAULT false,
    "LastUpdated" timestamp without time zone,
    "Enabled" boolean,
    "Source" character varying(4),
    "ErrorMessage" character varying(255)
);


ALTER TABLE "Condition" OWNER TO postgres;

--
-- Name: TABLE "Condition"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Condition" IS 'A condition is SET when a sensor value exceeds the Set Threshold, and is RESET when the value falls below the Reset threshold. Set and Reset can be overriden by more complicated expressions.';


--
-- Name: Event_EventID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Event_EventID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Event_EventID_seq" OWNER TO postgres;

--
-- Name: EventQueue; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "EventQueue" (
    "EventID" integer DEFAULT nextval('"Event_EventID_seq"'::regclass) NOT NULL,
    "SourceType" character varying(64),
    "SourceID" integer,
    "Timestamp" timestamp without time zone DEFAULT now(),
    "Value" integer,
    "Processed" boolean DEFAULT false,
    "SourceAgent" character varying(64)
);


ALTER TABLE "EventQueue" OWNER TO postgres;

--
-- Name: TABLE "EventQueue"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "EventQueue" IS 'An Event records when a condition/impulse changes state.';


--
-- Name: COLUMN "EventQueue"."Processed"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "EventQueue"."Processed" IS 'Has this Event been processed?';


--
-- Name: Impulse; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Impulse" (
    "ImpulseID" integer NOT NULL,
    "ImpulseName" character varying(64),
    "ImpulseDescription" character varying(255),
    "BCMPinNumber" integer,
    "LastUpdated" timestamp without time zone,
    "WebPresence" boolean DEFAULT true,
    "CurrentValue" boolean,
    "ZoneID" integer
);


ALTER TABLE "Impulse" OWNER TO postgres;

--
-- Name: TABLE "Impulse"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Impulse" IS 'An Impulse represents a non-deterministic digital input to the system e.g. Householder pressing a button';


--
-- Name: Impulse_ImpulseID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Impulse_ImpulseID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Impulse_ImpulseID_seq" OWNER TO postgres;

--
-- Name: Impulse_ImpulseID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Impulse_ImpulseID_seq" OWNED BY "Impulse"."ImpulseID";


--
-- Name: Measurand; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Measurand" (
    "MeasurandID" integer NOT NULL,
    "MeasurandName" character varying(128),
    "Units" character varying(16),
    "MaxValue" numeric(6,2),
    "MinValue" numeric(6,2),
    "GraphScaleMax" integer,
    "GraphScaleMin" integer,
    "TextUnits" character varying(16),
    "DecimalPlaces" integer DEFAULT 1
);


ALTER TABLE "Measurand" OWNER TO postgres;

--
-- Name: TABLE "Measurand"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Measurand" IS 'The Measurand defines a particular type of measurement property like temperature, humidity, etc.';


--
-- Name: COLUMN "Measurand"."DecimalPlaces"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "Measurand"."DecimalPlaces" IS 'Number of Decimal Places for measurand';


--
-- Name: Measurand_MeasurandID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Measurand_MeasurandID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Measurand_MeasurandID_seq" OWNER TO postgres;

--
-- Name: Measurand_MeasurandID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Measurand_MeasurandID_seq" OWNED BY "Measurand"."MeasurandID";


--
-- Name: Rule; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Rule" (
    "RuleID" integer NOT NULL,
    "RuleName" character varying(64),
    "RuleDescription" character varying(255),
    "SourceType" character varying(64),
    "SourceID" integer,
    "ActionID" integer,
    "Enabled" boolean DEFAULT true
);


ALTER TABLE "Rule" OWNER TO postgres;

--
-- Name: TABLE "Rule"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Rule" IS 'A Rule links a Condition/Impulse to an Action. The rule is the When clause.';


--
-- Name: Rule_RuleID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Rule_RuleID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Rule_RuleID_seq" OWNER TO postgres;

--
-- Name: Rule_RuleID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Rule_RuleID_seq" OWNED BY "Rule"."RuleID";


--
-- Name: Sample; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Sample" (
    "SampleID" integer NOT NULL,
    "Value" numeric(6,2),
    "Timestamp" timestamp without time zone DEFAULT now(),
    "SensorEntryID" integer
);


ALTER TABLE "Sample" OWNER TO postgres;

--
-- Name: TABLE "Sample"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Sample" IS 'A Sample will be the value of a Sensor at a particular time.';


--
-- Name: SampleDef; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "SampleDef" (
    "SampleDefID" integer NOT NULL,
    "SensorEntryID" integer,
    "SampleTypeID" integer
);


ALTER TABLE "SampleDef" OWNER TO postgres;

--
-- Name: TABLE "SampleDef"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "SampleDef" IS 'A SampleDef will be the definition of a Sensor that needs sampling.';


--
-- Name: COLUMN "SampleDef"."SampleDefID"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "SampleDef"."SampleDefID" IS ' ';


--
-- Name: SampleDef_SampleDefID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "SampleDef_SampleDefID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "SampleDef_SampleDefID_seq" OWNER TO postgres;

--
-- Name: SampleDef_SampleDefID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "SampleDef_SampleDefID_seq" OWNED BY "SampleDef"."SampleDefID";


--
-- Name: SampleType; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "SampleType" (
    "SampleTypeID" integer NOT NULL,
    "SampleTypeName" character varying(64),
    "SampleTypeDescription" character varying(256)
);


ALTER TABLE "SampleType" OWNER TO postgres;

--
-- Name: TABLE "SampleType"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "SampleType" IS 'A sample type may be raw or averaged or maybe smoothed in some other way.';


--
-- Name: COLUMN "SampleType"."SampleTypeID"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "SampleType"."SampleTypeID" IS 'Raw, averaged, predicted.';


--
-- Name: SampleType_SampleTypeID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "SampleType_SampleTypeID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "SampleType_SampleTypeID_seq" OWNER TO postgres;

--
-- Name: SampleType_SampleTypeID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "SampleType_SampleTypeID_seq" OWNED BY "SampleType"."SampleTypeID";


--
-- Name: Sample_SampleID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Sample_SampleID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Sample_SampleID_seq" OWNER TO postgres;

--
-- Name: Sample_SampleID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Sample_SampleID_seq" OWNED BY "Sample"."SampleID";


--
-- Name: Sensor; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Sensor" (
    "SensorEntryID" integer NOT NULL,
    "ZoneID" integer,
    "MeasurandID" integer,
    "CurrentValue" numeric(6,2) DEFAULT 0.0,
    "LastUpdated" timestamp without time zone,
    "SensorFunction" character varying(1024),
    "SensorDescription" character varying(255),
    "MaxDelta" numeric(6,2),
    "Enabled" boolean DEFAULT true,
    "Name" character varying(64),
    "SensorID" integer,
    "HighAlert" numeric(6,2),
    "LowAlert" numeric(6,2),
    "EmailRecipient" character varying(128),
    "TextRecipient" character varying(128),
    "ErrorMessage" character varying(255)
);


ALTER TABLE "Sensor" OWNER TO postgres;

--
-- Name: TABLE "Sensor"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Sensor" IS 'A Sensor is an input to the hub. It will have an ordinal ID, a nice name (derived from its type and zone e.g. Kitchen Temperature, Kitchen RH) and also a bus address. It will sense a Zone, and be of a SensorType. It will have a current reading, and a logging interval. 

Virtual Sensors such as Time Of Day, Day Of Week, and ad-hoc timer values, etc. will be supported.

';


--
-- Name: COLUMN "Sensor"."SensorID"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "Sensor"."SensorID" IS 'Fixed ID of each Sensor independent of row';


--
-- Name: Sensor_SensorEntryID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Sensor_SensorEntryID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Sensor_SensorEntryID_seq" OWNER TO postgres;

--
-- Name: Sensor_SensorEntryID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Sensor_SensorEntryID_seq" OWNED BY "Sensor"."SensorEntryID";


--
-- Name: Statistic; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Statistic" (
    "StatisticID" integer NOT NULL,
    "Value" numeric(6,2),
    "Timestamp" timestamp without time zone DEFAULT now(),
    "SensorEntryID" integer,
    "StatsFunction" "StatisticalFunction"
);


ALTER TABLE "Statistic" OWNER TO postgres;

--
-- Name: TABLE "Statistic"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Statistic" IS 'An Statistic is the maximum/minimum value of a Sensor over all time.';


--
-- Name: Statistic_StatisticID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Statistic_StatisticID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Statistic_StatisticID_seq" OWNER TO postgres;

--
-- Name: Statistic_StatisticID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Statistic_StatisticID_seq" OWNED BY "Statistic"."StatisticID";


--
-- Name: User; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "User" (
    "UserID" integer NOT NULL,
    "Username" character varying(32),
    "Password" character varying(40),
    "Role" character varying(64),
    "MobilePhoneNum" character varying(64)
);


ALTER TABLE "User" OWNER TO postgres;

--
-- Name: TABLE "User"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "User" IS 'Maintains user details';


--
-- Name: UserSetting; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "UserSetting" (
    "Name" character varying(24) NOT NULL,
    "Value" character varying(128)
);


ALTER TABLE "UserSetting" OWNER TO postgres;

--
-- Name: TABLE "UserSetting"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "UserSetting" IS 'Name Value pairs to provide User Settings';


--
-- Name: COLUMN "UserSetting"."Name"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "UserSetting"."Name" IS 'The Unique User Setting Name';


--
-- Name: COLUMN "UserSetting"."Value"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN "UserSetting"."Value" IS 'User Setting Value as a string';


--
-- Name: User_UserID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "User_UserID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "User_UserID_seq" OWNER TO postgres;

--
-- Name: User_UserID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "User_UserID_seq" OWNED BY "User"."UserID";


--
-- Name: Zone; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "Zone" (
    "ZoneID" integer NOT NULL,
    "ZoneName" character varying(128),
    "ZoneX" integer,
    "ZoneY" integer,
    "ZoneZ" integer DEFAULT 1,
    "ZoneRowspan" integer DEFAULT 1,
    "ZoneColspan" integer DEFAULT 1
);


ALTER TABLE "Zone" OWNER TO postgres;

--
-- Name: TABLE "Zone"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE "Zone" IS 'A Zone is a 3D geographical area being monitored e.g. a room, or the outside. It is primarily intended for gui presentation, enabling the house to be displayed as a plan and readings to be grouped.';


--
-- Name: Zone_ZoneID_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE "Zone_ZoneID_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "Zone_ZoneID_seq" OWNER TO postgres;

--
-- Name: Zone_ZoneID_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE "Zone_ZoneID_seq" OWNED BY "Zone"."ZoneID";


--
-- Name: vwActuators; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwActuators" AS
 SELECT a."ActuatorID",
    a."ActuatorName",
    a."ActuatorDescription",
    a."ActuatorTypeID",
    a."ActuatorFunction",
    t."ActuatorTypeName",
    t."ActuatorTypeDescription",
    a."IsInAuto",
    a."CurrentValue",
    a."OnForMins",
    a."OffForMins",
    a."ZoneID",
    z."ZoneName",
    a."LastUpdated",
    a."UpdatedBy",
    a."WebPresence"
   FROM (("Actuator" a
     JOIN "ActuatorType" t ON ((a."ActuatorTypeID" = t."ActuatorTypeID")))
     JOIN "Zone" z ON ((a."ZoneID" = z."ZoneID")))
  WHERE a."Enabled";


ALTER TABLE "vwActuators" OWNER TO postgres;

--
-- Name: vwAllConditions; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwAllConditions" AS
 SELECT c."ConditionID",
    c."ConditionName",
    c."ConditionDescription",
    c."SetOperator",
    c."SetThreshold",
    c."SetExpression",
    c."ResetOperator",
    c."ResetThreshold",
    c."ResetExpression",
    c."CurrentValue" AS "CurrentCondition",
    c."Source",
    "substring"((c."Source")::text, '[0-9]+'::text) AS "CalculatedSensorID",
    s."CurrentValue",
    c."LastUpdated",
    c."Enabled",
    m."Units",
        CASE
            WHEN ((c."Source" IS NOT NULL) AND (upper("left"(btrim((c."Source")::text), 1)) = 'T'::text)) THEN 'Time'::text
            WHEN ((((((c."Source")::text || (c."SetOperator")::text) || (c."SetThreshold")::text) || (c."ResetOperator")::text) || (c."ResetThreshold")::text) IS NOT NULL) THEN 'Simple'::text
            ELSE 'Complex'::text
        END AS "ConditionFormat"
   FROM (("Condition" c
     LEFT JOIN "Sensor" s ON ((("substring"((c."Source")::text, '[0-9]+'::text))::integer = s."SensorID")))
     LEFT JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")));


ALTER TABLE "vwAllConditions" OWNER TO postgres;

--
-- Name: vwAllEventRuleActions; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwAllEventRuleActions" AS
 SELECT e."EventID",
    e."Timestamp",
    e."SourceType",
    e."SourceID",
    e."SourceAgent",
    e."Value",
    r."RuleName",
    r."RuleDescription",
    a."ActionID",
    a."ActionName",
    a."ActionFunction",
    a."EmailRecipient",
    a."TextRecipient"
   FROM (("EventQueue" e
     JOIN "Rule" r ON ((((e."SourceType")::text = (r."SourceType")::text) AND (e."SourceID" = r."SourceID"))))
     JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
  ORDER BY e."EventID" DESC;


ALTER TABLE "vwAllEventRuleActions" OWNER TO postgres;

--
-- Name: vwComplexConditions; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwComplexConditions" AS
 SELECT c."ConditionID",
    c."ConditionName",
    c."ConditionDescription",
    c."CurrentValue" AS "CurrentCondition",
    c."SetExpression",
    c."ResetExpression",
        CASE
            WHEN ((c."Source" IS NOT NULL) AND (upper("left"(btrim((c."Source")::text), 1)) = 'T'::text)) THEN 'Time'::text
            WHEN ((((((c."Source")::text || (c."SetOperator")::text) || (c."SetThreshold")::text) || (c."ResetOperator")::text) || (c."ResetThreshold")::text) IS NOT NULL) THEN 'Simple'::text
            ELSE 'Complex'::text
        END AS "ConditionFormat"
   FROM "Condition" c
  WHERE (((c."SetExpression" IS NOT NULL) AND (c."ResetExpression" IS NOT NULL)) AND c."Enabled");


ALTER TABLE "vwComplexConditions" OWNER TO postgres;

--
-- Name: vwConditions; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwConditions" AS
 SELECT "vwAllConditions"."ConditionID",
    "vwAllConditions"."ConditionName",
    "vwAllConditions"."ConditionDescription",
    "vwAllConditions"."SetOperator",
    "vwAllConditions"."SetThreshold",
    "vwAllConditions"."SetExpression",
    "vwAllConditions"."ResetOperator",
    "vwAllConditions"."ResetThreshold",
    "vwAllConditions"."ResetExpression",
    "vwAllConditions"."CurrentCondition",
    "vwAllConditions"."Source",
    "vwAllConditions"."CalculatedSensorID",
    "vwAllConditions"."CurrentValue",
    "vwAllConditions"."LastUpdated",
    "vwAllConditions"."Enabled",
    "vwAllConditions"."Units",
    "vwAllConditions"."ConditionFormat"
   FROM "vwAllConditions"
  WHERE "vwAllConditions"."Enabled";


ALTER TABLE "vwConditions" OWNER TO postgres;

--
-- Name: vwCurrentInZone; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwCurrentInZone" AS
 SELECT z."ZoneID",
    z."ZoneName",
    z."ZoneX",
    z."ZoneY",
    z."ZoneZ",
    z."ZoneRowspan",
    z."ZoneColspan",
    s."SensorID",
    s."MeasurandID",
    s."CurrentValue",
    s."LastUpdated",
    m."MeasurandName",
    m."Units",
    s."SensorFunction"
   FROM (("Zone" z
     LEFT JOIN "Sensor" s ON ((s."ZoneID" = z."ZoneID")))
     LEFT JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  WHERE s."Enabled";


ALTER TABLE "vwCurrentInZone" OWNER TO postgres;

--
-- Name: vwCurrentInZoneForStats; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwCurrentInZoneForStats" AS
 SELECT z."ZoneID",
    (COALESCE(s."Name", z."ZoneName"))::character varying(128) AS "ZoneName",
    z."ZoneX",
    z."ZoneY",
    z."ZoneZ",
    z."ZoneRowspan",
    z."ZoneColspan",
    s."SensorID",
    s."MeasurandID",
    s."CurrentValue",
    s."LastUpdated",
    m."MeasurandName",
    m."Units",
    s."SensorFunction"
   FROM (("Zone" z
     LEFT JOIN "Sensor" s ON ((s."ZoneID" = z."ZoneID")))
     LEFT JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  WHERE s."Enabled";


ALTER TABLE "vwCurrentInZoneForStats" OWNER TO postgres;

--
-- Name: vwEventRuleActions; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwEventRuleActions" AS
 SELECT e."EventID",
    e."SourceType",
    e."SourceID",
    e."SourceAgent",
    e."Value",
    r."RuleName",
    r."RuleDescription",
    a."ActionID",
    a."ActionName",
    a."ActionFunction",
    a."EmailRecipient",
    a."TextRecipient"
   FROM (("EventQueue" e
     JOIN "Rule" r ON ((((e."SourceType")::text = (r."SourceType")::text) AND (e."SourceID" = r."SourceID"))))
     JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
  WHERE (r."Enabled" AND (NOT e."Processed"))
  ORDER BY e."EventID";


ALTER TABLE "vwEventRuleActions" OWNER TO postgres;

--
-- Name: vwEventsForGraph; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwEventsForGraph" AS
 SELECT e."SourceID" AS "ConditionID",
    c."Source" AS "SensorID",
    (e."Timestamp" - '00:00:01'::interval) AS "Timestamp",
    (e."Value" # 1) AS "Value"
   FROM ("EventQueue" e
     JOIN "Condition" c ON (((e."SourceID" = c."ConditionID") AND ((e."SourceType")::text = 'Condition'::text))))
  WHERE (NOT (e."EventID" IN ( SELECT min(q."EventID") AS min
           FROM "EventQueue" q)))
UNION
 SELECT e."SourceID" AS "ConditionID",
    c."Source" AS "SensorID",
    e."Timestamp",
    e."Value"
   FROM ("EventQueue" e
     JOIN "Condition" c ON (((e."SourceID" = c."ConditionID") AND ((e."SourceType")::text = 'Condition'::text))))
  WHERE (NOT (e."EventID" IN ( SELECT min(q."EventID") AS min
           FROM "EventQueue" q)))
UNION
 (WITH max_timestamp AS (
	SELECT "SourceID", "SourceType", max("Timestamp") AS max
	FROM "EventQueue"
	GROUP BY "SourceID", "SourceType"
 )
 SELECT e1."SourceID" AS "ConditionID",
    c."Source" AS "SensorID",
    now() AS "Timestamp",
    e1."Value"
   FROM "EventQueue" e1
     JOIN "Condition" c ON e1."SourceID" = c."ConditionID" AND e1."SourceType"::text = 'Condition'::text
     LEFT JOIN "Sensor" s ON "substring"(c."Source"::text, '[0-9]+'::text)::integer = s."SensorID"
     JOIN max_timestamp t ON 	e1."SourceID" = t."SourceID" AND 
				e1."SourceType"::text = t."SourceType"::text AND
				e1."Timestamp" = t."max")
ORDER BY 1, 3;


ALTER TABLE "vwEventsForGraph" OWNER TO postgres;

--
-- Name: vwImpulseRuleActionActuator; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwImpulseRuleActionActuator" AS
 SELECT i."ImpulseID",
    i."ImpulseName",
    i."ImpulseDescription",
    i."BCMPinNumber",
    i."LastUpdated",
    i."WebPresence",
    "substring"((a."ActionFunction")::text, '%actuator.#"%#"'::text, '#'::text) AS "Actuator",
    t."CurrentValue",
    i."ZoneID" AS "ImpulseZone",
    z."ZoneID" AS "ActuatorZoneID"
   FROM (((("Impulse" i
     JOIN "Rule" r ON (((r."SourceID" = i."ImpulseID") AND ((r."SourceType")::text = 'Impulse'::text))))
     JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
     JOIN "Actuator" t ON ((("substring"((a."ActionFunction")::text, '%actuator.#"%#"'::text, '#'::text))::integer = t."ActuatorID")))
     JOIN "Zone" z ON ((t."ZoneID" = z."ZoneID")));


ALTER TABLE "vwImpulseRuleActionActuator" OWNER TO postgres;

--
-- Name: vwMapZone; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapZone" AS
 SELECT ('Z'::text || z."ZoneID") AS "ChainNum",
    ((('Z'::text || z."ZoneID") || ' '::text) || (z."ZoneName")::text) AS "NodeID",
    true AS "Enabled",
    z."ZoneName" AS "Description",
    ''::text AS "ParentID",
    ('Z'::text || z."ZoneID") AS "DbId"
   FROM "Zone" z
  ORDER BY z."ZoneID";


ALTER TABLE "vwMapZone" OWNER TO postgres;

--
-- Name: vwMapBase; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapBase" AS
 SELECT ('S'::text || s."SensorID") AS "ChainNum",
    ((((('S'::text || s."SensorID") || ' '::text) || (COALESCE(s."Name", z."ZoneName"))::text) || ' '::text) || (m."MeasurandName")::text) AS "SensorName",
    s."Enabled" AS "SensorEnabled",
    s."SensorDescription",
    ('S'::text || s."SensorID") AS "SDbId",
    ((('C'::text || c."ConditionID") || ' '::text) || (c."ConditionName")::text) AS "ConditionName",
    c."Enabled" AS "ConditionEnabled",
    c."ConditionDescription",
    ('C'::text || c."ConditionID") AS "CDbId",
    ((('R'::text || r."RuleID") || ' '::text) || (r."RuleName")::text) AS "RuleName",
    r."Enabled" AS "RuleEnabled",
    r."RuleDescription",
    ('R'::text || r."RuleID") AS "RDbId",
    ((('A'::text || a."ActionID") || ' '::text) || (a."ActionName")::text) AS "ActionName",
    a."Enabled" AS "ActionEnabled",
    a."ActionDescription",
    ('A'::text || a."ActionID") AS "ADbId",
    ((('U'::text || t."ActuatorID") || ' '::text) || (t."ActuatorName")::text) AS "ActuatorName",
    t."Enabled" AS "ActuatorEnabled",
    t."ActuatorDescription",
    ('U'::text || t."ActuatorID") AS "TDbId"
   FROM (((((("Sensor" s
     LEFT JOIN "Condition" c ON (((c."SetExpression")::text ~ (('S'::text || s."SensorID") || '(\D)'::text))))
     LEFT JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
     LEFT JOIN "Measurand" m ON ((m."MeasurandID" = s."MeasurandID")))
     LEFT JOIN "Rule" r ON ((((r."SourceType")::text = 'Condition'::text) AND (r."SourceID" = c."ConditionID"))))
     LEFT JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
     LEFT JOIN "Actuator" t ON (((a."ActionFunction")::text ~ (('.*'::text || t."ActuatorID") || '$'::text))))
  WHERE ((r."RuleID" IS NOT NULL) AND (c."ConditionID" IS NOT NULL))
UNION
 SELECT ('I'::text || i."ImpulseID") AS "ChainNum",
    ((('I'::text || i."ImpulseID") || ' '::text) || (i."ImpulseName")::text) AS "SensorName",
    true AS "SensorEnabled",
    i."ImpulseDescription" AS "SensorDescription",
    ('I'::text || i."ImpulseID") AS "SDbId",
    ((('R'::text || r."RuleID") || ' '::text) || (r."RuleName")::text) AS "ConditionName",
    r."Enabled" AS "ConditionEnabled",
    r."RuleDescription" AS "ConditionDescription",
    ('R'::text || r."RuleID") AS "CDbId",
    ((('A'::text || a."ActionID") || ' '::text) || (a."ActionName")::text) AS "RuleName",
    a."Enabled" AS "RuleEnabled",
    a."ActionDescription" AS "RuleDescription",
    ('A'::text || a."ActionID") AS "RDbId",
    ((('U'::text || t."ActuatorID") || ' '::text) || (t."ActuatorName")::text) AS "ActionName",
    t."Enabled" AS "ActionEnabled",
    t."ActuatorDescription" AS "ActionDescription",
    ('U'::text || t."ActuatorID") AS "ADbId",
    NULL::text AS "ActuatorName",
    false AS "ActuatorEnabled",
    NULL::character varying AS "ActuatorDescription",
    NULL::text AS "TDbId"
   FROM (((("Impulse" i
     LEFT JOIN "Rule" r ON ((((r."SourceType")::text = 'Impulse'::text) AND (r."SourceID" = i."ImpulseID"))))
     LEFT JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
     LEFT JOIN "Actuator" t ON (((a."ActionFunction")::text ~ (('.*'::text || t."ActuatorID") || '$'::text))))
     LEFT JOIN "Zone" z ON ((t."ZoneID" = z."ZoneID")))
  WHERE (r."RuleID" IS NOT NULL)
UNION
 SELECT ('C'::text || c."ConditionID") AS "ChainNum",
    ((('C'::text || c."ConditionID") || ' '::text) || (c."ConditionName")::text) AS "SensorName",
    c."Enabled" AS "SensorEnabled",
    c."ConditionDescription" AS "SensorDescription",
    ('C'::text || c."ConditionID") AS "SDbId",
    ((('R'::text || r."RuleID") || ' '::text) || (r."RuleName")::text) AS "ConditionName",
    r."Enabled" AS "ConditionEnabled",
    r."RuleDescription" AS "ConditionDescription",
    ('R'::text || r."RuleID") AS "CDbId",
    ((('A'::text || a."ActionID") || ' '::text) || (a."ActionName")::text) AS "RuleName",
    a."Enabled" AS "RuleEnabled",
    a."ActionDescription" AS "RuleDescription",
    ('A'::text || a."ActionID") AS "RDbId",
    ((('U'::text || t."ActuatorID") || ' '::text) || (t."ActuatorName")::text) AS "ActionName",
    t."Enabled" AS "ActionEnabled",
    t."ActuatorDescription" AS "ActionDescription",
    ('U'::text || t."ActuatorID") AS "ADbId",
    NULL::text AS "ActuatorName",
    false AS "ActuatorEnabled",
    NULL::character varying AS "ActuatorDescription",
    NULL::text AS "TDbId"
   FROM (((("Condition" c
     LEFT JOIN "Rule" r ON ((((r."SourceType")::text = 'Condition'::text) AND (r."SourceID" = c."ConditionID"))))
     LEFT JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
     LEFT JOIN "Actuator" t ON (((a."ActionFunction")::text ~ (('.*'::text || t."ActuatorID") || '$'::text))))
     LEFT JOIN "Zone" z ON ((t."ZoneID" = z."ZoneID")))
  WHERE (((c."Source")::text ~~ 'TO%'::text) AND (r."RuleID" IS NOT NULL))
  ORDER BY 1;


ALTER TABLE "vwMapBase" OWNER TO postgres;

--
-- Name: vwMapOrg; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapOrg" AS
 SELECT DISTINCT "vwMapBase"."ChainNum",
    "vwMapBase"."SensorName" AS "NodeID",
    "vwMapBase"."SensorEnabled" AS "Enabled",
    "vwMapBase"."SensorDescription" AS "Description",
    ''::text AS "ParentID",
    "vwMapBase"."SDbId" AS "DbId"
   FROM "vwMapBase"
UNION
 SELECT DISTINCT "vwMapBase"."ChainNum",
    "vwMapBase"."ConditionName" AS "NodeID",
    "vwMapBase"."ConditionEnabled" AS "Enabled",
    "vwMapBase"."ConditionDescription" AS "Description",
    "vwMapBase"."SensorName" AS "ParentID",
    "vwMapBase"."CDbId" AS "DbId"
   FROM "vwMapBase"
UNION
 SELECT DISTINCT "vwMapBase"."ChainNum",
    "vwMapBase"."RuleName" AS "NodeID",
    "vwMapBase"."RuleEnabled" AS "Enabled",
    "vwMapBase"."RuleDescription" AS "Description",
    "vwMapBase"."ConditionName" AS "ParentID",
    "vwMapBase"."RDbId" AS "DbId"
   FROM "vwMapBase"
UNION
 SELECT DISTINCT "vwMapBase"."ChainNum",
    "vwMapBase"."ActionName" AS "NodeID",
    "vwMapBase"."ActionEnabled" AS "Enabled",
    "vwMapBase"."ActionDescription" AS "Description",
    "vwMapBase"."RuleName" AS "ParentID",
    "vwMapBase"."ADbId" AS "DbId"
   FROM "vwMapBase"
UNION
 SELECT DISTINCT "vwMapBase"."ChainNum",
    "vwMapBase"."ActuatorName" AS "NodeID",
    "vwMapBase"."ActuatorEnabled" AS "Enabled",
    "vwMapBase"."ActuatorDescription" AS "Description",
    "vwMapBase"."ActionName" AS "ParentID",
    "vwMapBase"."TDbId" AS "DbId"
   FROM "vwMapBase"
  ORDER BY 2, 1;


ALTER TABLE "vwMapOrg" OWNER TO postgres;

--
-- Name: vwMapSensor; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapSensor" AS
 SELECT ('M'::text || m."MeasurandID") AS "ChainNum",
    ((('M'::text || m."MeasurandID") || ' '::text) || (m."MeasurandName")::text) AS "NodeID",
    true AS "Enabled",
    m."MeasurandName" AS "Description",
    ''::text AS "ParentID",
    ('M'::text || m."MeasurandID") AS "DbId"
   FROM "Measurand" m
UNION
 SELECT ('M'::text || m."MeasurandID") AS "ChainNum",
    ((((('S'::text || s."SensorID") || ' '::text) || (COALESCE(s."Name", z."ZoneName"))::text) || ' '::text) || (m."MeasurandName")::text) AS "NodeID",
    s."Enabled",
    s."SensorDescription" AS "Description",
    ((('M'::text || m."MeasurandID") || ' '::text) || (m."MeasurandName")::text) AS "ParentID",
    ('S'::text || s."SensorID") AS "DbId"
   FROM (("Sensor" s
     LEFT JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
     LEFT JOIN "Measurand" m ON ((m."MeasurandID" = s."MeasurandID")))
  ORDER BY 6;


ALTER TABLE "vwMapSensor" OWNER TO postgres;

--
-- Name: vwMapActuator; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapActuator" AS
 SELECT ('Y'::text || y."ActuatorTypeID") AS "ChainNum",
    ((('Y'::text || y."ActuatorTypeID") || ' '::text) || (y."ActuatorTypeName")::text) AS "NodeID",
    true AS "Enabled",
    y."ActuatorTypeName" AS "Description",
    ''::text AS "ParentID",
    ('Y'::text || y."ActuatorTypeID") AS "DbId"
   FROM "ActuatorType" y
UNION
 SELECT ('Y'::text || y."ActuatorTypeID") AS "ChainNum",
    ((((('U'::text || t."ActuatorID") || ' '::text) || (COALESCE(t."ActuatorName", z."ZoneName"))::text) || ' '::text) || (y."ActuatorTypeName")::text) AS "NodeID",
    t."Enabled",
    t."ActuatorDescription" AS "Description",
    ((('Y'::text || t."ActuatorTypeID") || ' '::text) || (y."ActuatorTypeName")::text) AS "ParentID",
    ('U'::text || t."ActuatorID") AS "DbId"
   FROM (("Actuator" t
     LEFT JOIN "Zone" z ON ((t."ZoneID" = z."ZoneID")))
     LEFT JOIN "ActuatorType" y ON ((t."ActuatorTypeID" = y."ActuatorTypeID")))
  ORDER BY 6;


ALTER TABLE "vwMapActuator" OWNER TO postgres;

--
-- Name: vwMapImpulse; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapImpulse" AS
 SELECT 'I'::text || i."ImpulseID" AS "ChainNum",
    (('I'::text || i."ImpulseID") || ' '::text) || i."ImpulseName"::text AS "NodeID",
    true AS "Enabled",
    i."ImpulseDescription" AS "Description",
    ''::text AS "ParentID",
    'I'::text || i."ImpulseID" AS "DbId"
   FROM "Impulse" i
  ORDER BY i."ImpulseID";


ALTER TABLE "vwMapImpulse" OWNER TO postgres;

--
-- Name: vwMapUser; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapUser" AS
 SELECT ('P'::text || u."UserID") AS "ChainNum",
    ((('User-'::text || u."UserID") || ' '::text) || (u."Username")::text) AS "NodeID",
    true AS "Enabled",
    u."Role" AS "Description",
    ''::text AS "ParentID",
    ('P'::text || u."UserID") AS "DbId"
   FROM "User" u
  ORDER BY u."UserID";


ALTER TABLE "vwMapUser" OWNER TO postgres;


--
-- Name: vwMapRule; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapRule" AS 
 SELECT 'R'::text || r."RuleID" AS "ChainNum",
    (('R'::text || r."RuleID") || ' '::text) || r."RuleName"::text AS "NodeID",
    r."Enabled",
    r."RuleName" AS "Description",
    ''::text AS "ParentID",
    'R'::text || r."RuleID" AS "DbId"
   FROM "Rule" r
  ORDER BY r."RuleID";

ALTER TABLE "vwMapRule" OWNER TO postgres;


--
-- Name: vwMapAction; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMapAction" AS 
 SELECT 'A'::text || a."ActionID" AS "ChainNum",
    (('A'::text || a."ActionID") || ' '::text) || a."ActionName"::text AS "NodeID",
    a."Enabled",
    a."ActionName" AS "Description",
    ''::text AS "ParentID",
    'A'::text || a."ActionID" AS "DbId"
   FROM "Action" a
  ORDER BY a."ActionID";

ALTER TABLE "vwMapAction" OWNER TO postgres;

--
-- Name: vwMaxValues; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMaxValues" AS
 SELECT "vwCurrentInZone"."MeasurandName",
    max("vwCurrentInZone"."CurrentValue") AS "MaxValue",
    first("vwCurrentInZone"."Units") AS "Units"
   FROM "vwCurrentInZone"
  GROUP BY "vwCurrentInZone"."MeasurandName"
 HAVING (count("vwCurrentInZone"."MeasurandName") > 1);


ALTER TABLE "vwMaxValues" OWNER TO postgres;

--
-- Name: vwMinValues; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwMinValues" AS
 SELECT "vwCurrentInZone"."MeasurandName",
    min("vwCurrentInZone"."CurrentValue") AS "MinValue",
    first("vwCurrentInZone"."Units") AS "Units"
   FROM "vwCurrentInZone"
  GROUP BY "vwCurrentInZone"."MeasurandName"
 HAVING (count("vwCurrentInZone"."MeasurandName") > 1);


ALTER TABLE "vwMinValues" OWNER TO postgres;

--
-- Name: vwRules; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwRules" AS
 SELECT r."RuleID",
    r."RuleName",
    r."RuleDescription",
    r."SourceType",
    r."SourceID",
    r."ActionID",
    a."ActionName" AS "Action Name",
    a."ActionDescription",
    a."ActionFunction"
   FROM ("Rule" r
     JOIN "Action" a ON ((r."ActionID" = a."ActionID")))
  WHERE r."Enabled";


ALTER TABLE "vwRules" OWNER TO postgres;

--
-- Name: vwSampleDefs; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSampleDefs" AS
 SELECT d."SampleDefID",
    d."SampleTypeID",
    m."SampleTypeName",
    d."SensorEntryID",
    s."CurrentValue",
    u."MaxValue",
    u."MinValue"
   FROM ((("SampleDef" d
     JOIN "Sensor" s ON ((d."SensorEntryID" = s."SensorEntryID")))
     JOIN "SampleType" m ON ((d."SampleTypeID" = m."SampleTypeID")))
     JOIN "Measurand" u ON ((s."MeasurandID" = u."MeasurandID")));


ALTER TABLE "vwSampleDefs" OWNER TO postgres;

--
-- Name: vwSamples; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSamples" AS
 SELECT '1'::text AS "Index",
    s."Timestamp",
    to_char((s."Timestamp" - '1 mon'::interval), '"Date("YYYY,FMMM,FMDD,FMHH24,FMMI,1)'::text) AS "Timebase",
    s."SensorEntryID",
    n."SensorID",
    n."ZoneID",
    m."MeasurandID",
    s."Value"
   FROM (("Sample" s
     JOIN "Sensor" n ON ((s."SensorEntryID" = n."SensorEntryID")))
     JOIN "Measurand" m ON ((n."MeasurandID" = m."MeasurandID")))
  WHERE n."Enabled";


ALTER TABLE "vwSamples" OWNER TO postgres;

--
-- Name: vwSensorList; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSensorList" AS
 SELECT ((((('S'::text || s."SensorID") || ' - '::text) || (COALESCE(s."Name", z."ZoneName"))::text) || ' '::text) || (m."MeasurandName")::text) AS "SensorName",
    ('S'::text || s."SensorID") AS "SensorRef"
   FROM (("Sensor" s
     JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
     JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  ORDER BY s."SensorID";


ALTER TABLE "vwSensorList" OWNER TO postgres;

--
-- Name: vwSensorMeasurands; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSensorMeasurands" AS
 SELECT s."SensorID",
    m."MeasurandID",
    m."MeasurandName",
    m."Units",
    m."GraphScaleMax",
    m."GraphScaleMin"
   FROM ("Sensor" s
     JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  ORDER BY m."MeasurandID";


ALTER TABLE "vwSensorMeasurands" OWNER TO postgres;

--
-- Name: vwSensors; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSensors" AS
 SELECT s."SensorEntryID",
 	s."SensorID",
    s."CurrentValue",
    s."LastUpdated",
    s."MeasurandID",
    m."MeasurandName",
    z."ZoneID",
    z."ZoneName",
    s."Name",
    ((((((z."ZoneName")::text || ' '::text) || (m."MeasurandName")::text) || ' ['::text) || (m."Units")::text) || ']'::text) AS "SensorName"
   FROM (("Sensor" s
     JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
     JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  WHERE s."Enabled";


ALTER TABLE "vwSensors" OWNER TO postgres;

--
-- Name: vwSensorsAndTypes; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSensorsAndTypes" AS
 SELECT s."SensorID",
    s."Name",
    (((COALESCE(s."Name", z."ZoneName"))::text || ' '::text) || (m."MeasurandName")::text) AS "SensorTitle",
    s."CurrentValue",
    s."LastUpdated",
    s."ZoneID",
    s."SensorFunction",
    s."MaxDelta",
    s."HighAlert",
    s."LowAlert",
    s."EmailRecipient",
    s."TextRecipient",
    m."MeasurandID",
    m."MeasurandName",
    m."Units",
    m."TextUnits",
    m."MinValue",
    m."MaxValue",
    m."DecimalPlaces"
   FROM (("Sensor" s
     JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
     JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
  WHERE s."Enabled";


ALTER TABLE "vwSensorsAndTypes" OWNER TO postgres;

--
-- Name: vwSources; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwSources" AS
 SELECT 'Condition'::text AS "SourceType",
    "Condition"."ConditionID" AS "SourceID",
    "Condition"."ConditionName" AS "SourceName"
   FROM "Condition"
UNION
 SELECT 'Impulse'::text AS "SourceType",
    "Impulse"."ImpulseID" AS "SourceID",
    "Impulse"."ImpulseName" AS "SourceName"
   FROM "Impulse"
  ORDER BY 1, 2;


ALTER TABLE "vwSources" OWNER TO postgres;

--
-- Name: vwTodaysMaximums; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwTodaysMaximums" AS
 SELECT t."StatisticID",
    t."Value",
    t."Timestamp",
    "SensorID",
    t."StatsFunction",
    (((COALESCE(s."Name", z."ZoneName"))::text || ' '::text) || (m."MeasurandName")::text) AS "SensorTitle",
    m."Units",
    m."TextUnits"
   FROM ((("Sensor" s
     JOIN "Statistic" t ON ((s."SensorEntryID" = t."SensorEntryID")))
     JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
     JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  WHERE ((t."Timestamp" >= ((now())::date - '24:00:00'::interval)) AND (t."StatsFunction" = 'Maximum'::"StatisticalFunction"));


ALTER TABLE "vwTodaysMaximums" OWNER TO postgres;

--
-- Name: vwTodaysMinimums; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwTodaysMinimums" AS
 SELECT t."StatisticID",
    t."Value",
    t."Timestamp",
    "SensorID",
    t."StatsFunction",
    (((COALESCE(s."Name", z."ZoneName"))::text || ' '::text) || (m."MeasurandName")::text) AS "SensorTitle",
    m."Units",
    m."TextUnits"
   FROM ((("Sensor" s
     JOIN "Statistic" t ON ((s."SensorEntryID" = t."SensorEntryID")))
     JOIN "Zone" z ON ((s."ZoneID" = z."ZoneID")))
     JOIN "Measurand" m ON ((s."MeasurandID" = m."MeasurandID")))
  WHERE ((t."Timestamp" >= ((now())::date - '24:00:00'::interval)) AND (t."StatsFunction" = 'Minimum'::"StatisticalFunction"));


ALTER TABLE "vwTodaysMinimums" OWNER TO postgres;


--
-- Name: vwStatistics; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwStatistics" AS
SELECT "StatisticID", "Value", "Timestamp", s."SensorID", "StatsFunction"
  FROM "Statistic" t
  INNER JOIN "Sensor" s ON t."SensorEntryID" = s."SensorEntryID";


ALTER TABLE "vwStatistics" OWNER TO postgres;


--
-- Name: vwZoneMaxValues; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwZoneMaxValues" AS
 SELECT c1."MeasurandName",
    c1."MaxValue",
    c1."Units",
    c2."ZoneName"
   FROM ("vwMaxValues" c1
     JOIN "vwCurrentInZoneForStats" c2 ON ((c1."MaxValue" = c2."CurrentValue")));


ALTER TABLE "vwZoneMaxValues" OWNER TO postgres;

--
-- Name: vwZoneMinValues; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwZoneMinValues" AS
 SELECT c1."MeasurandName",
    c1."MinValue",
    c1."Units",
    c2."ZoneName"
   FROM ("vwMinValues" c1
     JOIN "vwCurrentInZoneForStats" c2 ON ((c1."MinValue" = c2."CurrentValue")));


ALTER TABLE "vwZoneMinValues" OWNER TO postgres;

--
-- Name: vwZoneMaxMinValues; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW "vwZoneMaxMinValues" AS
 SELECT x."MeasurandName",
    x."Units",
    x."MaxValue",
    x."ZoneName" AS "MaxZone",
    n."MinValue",
    n."ZoneName" AS "MinZone"
   FROM ("vwZoneMaxValues" x
     JOIN "vwZoneMinValues" n ON (((x."MeasurandName")::text = (n."MeasurandName")::text)));


ALTER TABLE "vwZoneMaxMinValues" OWNER TO postgres;

--
-- Name: ActionID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Action" ALTER COLUMN "ActionID" SET DEFAULT nextval('"Action_ActionID_seq"'::regclass);


--
-- Name: ActuatorEntryID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Actuator" ALTER COLUMN "ActuatorEntryID" SET DEFAULT nextval('"Actuator_ActuatorEntryID_seq"'::regclass);


--
-- Name: ActuatorTypeID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "ActuatorType" ALTER COLUMN "ActuatorTypeID" SET DEFAULT nextval('"ActuatorType_ActuatorTypeID_seq"'::regclass);


--
-- Name: ImpulseID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Impulse" ALTER COLUMN "ImpulseID" SET DEFAULT nextval('"Impulse_ImpulseID_seq"'::regclass);


--
-- Name: MeasurandID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Measurand" ALTER COLUMN "MeasurandID" SET DEFAULT nextval('"Measurand_MeasurandID_seq"'::regclass);


--
-- Name: RuleID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Rule" ALTER COLUMN "RuleID" SET DEFAULT nextval('"Rule_RuleID_seq"'::regclass);


--
-- Name: SampleID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Sample" ALTER COLUMN "SampleID" SET DEFAULT nextval('"Sample_SampleID_seq"'::regclass);


--
-- Name: SampleDefID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "SampleDef" ALTER COLUMN "SampleDefID" SET DEFAULT nextval('"SampleDef_SampleDefID_seq"'::regclass);


--
-- Name: SampleTypeID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "SampleType" ALTER COLUMN "SampleTypeID" SET DEFAULT nextval('"SampleType_SampleTypeID_seq"'::regclass);


--
-- Name: SensorEntryID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Sensor" ALTER COLUMN "SensorEntryID" SET DEFAULT nextval('"Sensor_SensorEntryID_seq"'::regclass);


--
-- Name: StatisticID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Statistic" ALTER COLUMN "StatisticID" SET DEFAULT nextval('"Statistic_StatisticID_seq"'::regclass);


--
-- Name: UserID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "User" ALTER COLUMN "UserID" SET DEFAULT nextval('"User_UserID_seq"'::regclass);


--
-- Name: ZoneID; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Zone" ALTER COLUMN "ZoneID" SET DEFAULT nextval('"Zone_ZoneID_seq"'::regclass);


--
-- Data for Name: Action; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Action" ("ActionID", "ActionName", "ActionDescription", "ActionFunction", "Enabled", "EmailRecipient", "TextRecipient") FROM stdin;
1	Action	Exemplar	action_actuator.1	f		
\.


--
-- Name: Action_ActionID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Action_ActionID_seq"', 1, true);


--
-- Data for Name: Actuator; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Actuator" ("ActuatorEntryID", "ActuatorDescription", "ActuatorTypeID", "CurrentValue", "ZoneID", "ActuatorName", "IsInAuto", "LastUpdated", "UpdatedBy", "ActuatorID", "WebPresence", "Enabled", "ActuatorFunction", "OnForMins", "OffForMins") FROM stdin;
1	Exemplar	1	0.00	1	Actuator	N	2017-04-13 10:36:36.988459	Hub	1	t	f	simple_on_off.2	-1	0
\.


--
-- Data for Name: ActuatorType; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "ActuatorType" ("ActuatorTypeID", "ActuatorTypeName", "ActuatorTypeDescription") FROM stdin;
1	Relay-Operated	Relay-operated digital output
\.


--
-- Name: ActuatorType_ActuatorTypeID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"ActuatorType_ActuatorTypeID_seq"', 1, true);


--
-- Name: Actuator_ActuatorEntryID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Actuator_ActuatorEntryID_seq"', 1, true);


--
-- Data for Name: Condition; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Condition" ("ConditionID", "ConditionName", "ConditionDescription", "SetOperator", "SetThreshold", "SetExpression", "ResetOperator", "ResetThreshold", "ResetExpression", "CurrentValue", "LastUpdated", "Enabled", "Source", "ErrorMessage") FROM stdin;
1	Condition	Exemplar	>	0.8	S50 > 0.8	<	0.4	S50 < 0.4	f	\N	f	S50	\N
\.


--
-- Name: Condition_ConditionID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Condition_ConditionID_seq"', 1, true);


--
-- Data for Name: EventQueue; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "EventQueue" ("EventID", "SourceType", "SourceID", "Timestamp", "Value", "Processed", "SourceAgent") FROM stdin;
\.


--
-- Name: Event_EventID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Event_EventID_seq"', 1, false);


--
-- Data for Name: Impulse; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Impulse" ("ImpulseID", "ImpulseName", "ImpulseDescription", "BCMPinNumber", "LastUpdated", "WebPresence", "CurrentValue", "ZoneID") FROM stdin;
\.


--
-- Name: Impulse_ImpulseID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Impulse_ImpulseID_seq"', 1, false);


--
-- Data for Name: Measurand; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Measurand" ("MeasurandID", "MeasurandName", "Units", "MaxValue", "MinValue", "GraphScaleMax", "GraphScaleMin", "TextUnits", "DecimalPlaces") FROM stdin;
1	Angle	units	10.00	-10.00	1	-1	units	1
2	Temperature	°C	44.00	-20.00	45	-15	degrees C	1
\.


--
-- Name: Measurand_MeasurandID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Measurand_MeasurandID_seq"', 2, true);


--
-- Data for Name: Rule; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Rule" ("RuleID", "RuleName", "RuleDescription", "SourceType", "SourceID", "ActionID", "Enabled") FROM stdin;
1	Rule	Exemplar	Condition	1	1	f
\.


--
-- Name: Rule_RuleID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Rule_RuleID_seq"', 1, true);


--
-- Data for Name: Sample; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Sample" ("SampleID", "Value", "Timestamp", "SensorEntryID") FROM stdin;
\.


--
-- Data for Name: SampleDef; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "SampleDef" ("SampleDefID", "SensorEntryID", "SampleTypeID") FROM stdin;
\.


--
-- Name: SampleDef_SampleDefID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"SampleDef_SampleDefID_seq"', 1, false);


--
-- Data for Name: SampleType; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "SampleType" ("SampleTypeID", "SampleTypeName", "SampleTypeDescription") FROM stdin;
1	Raw	Just sample the current value
\.


--
-- Name: SampleType_SampleTypeID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"SampleType_SampleTypeID_seq"', 1, true);


--
-- Name: Sample_SampleID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Sample_SampleID_seq"', 1, false);


--
-- Data for Name: Sensor; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Sensor" ("SensorEntryID", "ZoneID", "MeasurandID", "CurrentValue", "LastUpdated", "SensorFunction", "SensorDescription", "MaxDelta", "Enabled", "Name", "SensorID", "HighAlert", "LowAlert", "EmailRecipient", "TextRecipient", "ErrorMessage") FROM stdin;
1	1	1	0.21	2017-03-23 09:02:01.395307	sin(datetime.datetime.now().minute*2*pi/60.0)	Sinewave	\N	t	Sinewave	50	\N	\N	\N	\N	\N
\.


--
-- Name: Sensor_SensorEntryID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Sensor_SensorEntryID_seq"', 1, true);


--
-- Data for Name: Statistic; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Statistic" ("StatisticID", "Value", "Timestamp", "SensorEntryID", "StatsFunction") FROM stdin;
\.


--
-- Name: Statistic_StatisticID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Statistic_StatisticID_seq"', 1, false);


--
-- Data for Name: User; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "User" ("UserID", "Username", "Password", "Role", "MobilePhoneNum") FROM stdin;
1	pi	eaca980117c022577f5b2fdce33242bd13148447	admin	\N
\.


--
-- Data for Name: UserSetting; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "UserSetting" ("Name", "Value") FROM stdin;
AdminRecipient	\N
MailServer	\N
EmailFrom	home-hub@example.com
EmailSender	\N
SummaryEnabled	False
TextProvider	\N
\.


--
-- Name: User_UserID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"User_UserID_seq"', 1, true);


--
-- Data for Name: Zone; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "Zone" ("ZoneID", "ZoneName", "ZoneX", "ZoneY", "ZoneZ", "ZoneRowspan", "ZoneColspan") FROM stdin;
1	Kitchen	0	0	1	1	1
2	Bathroom	1	0	1	1	1
\.


--
-- Name: Zone_ZoneID_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('"Zone_ZoneID_seq"', 2, true);


--
-- Name: Action_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Action"
    ADD CONSTRAINT "Action_pkey" PRIMARY KEY ("ActionID");


--
-- Name: ActuatorType_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "ActuatorType"
    ADD CONSTRAINT "ActuatorType_pkey" PRIMARY KEY ("ActuatorTypeID");


--
-- Name: Actuator_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Actuator"
    ADD CONSTRAINT "Actuator_pkey" PRIMARY KEY ("ActuatorEntryID");


--
-- Name: Condition_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Condition"
    ADD CONSTRAINT "Condition_pkey" PRIMARY KEY ("ConditionID");


--
-- Name: Event_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "EventQueue"
    ADD CONSTRAINT "Event_pkey" PRIMARY KEY ("EventID");


--
-- Name: Impulse_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Impulse"
    ADD CONSTRAINT "Impulse_pkey" PRIMARY KEY ("ImpulseID");


--
-- Name: Measurand_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Measurand"
    ADD CONSTRAINT "Measurand_pkey" PRIMARY KEY ("MeasurandID");


--
-- Name: Rule_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Rule"
    ADD CONSTRAINT "Rule_pkey" PRIMARY KEY ("RuleID");


--
-- Name: SampleDef_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "SampleDef"
    ADD CONSTRAINT "SampleDef_pkey" PRIMARY KEY ("SampleDefID");


--
-- Name: SampleType_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "SampleType"
    ADD CONSTRAINT "SampleType_pkey" PRIMARY KEY ("SampleTypeID");


--
-- Name: Sample_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Sample"
    ADD CONSTRAINT "Sample_pkey" PRIMARY KEY ("SampleID");


--
-- Name: Sensor_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Sensor"
    ADD CONSTRAINT "Sensor_pkey" PRIMARY KEY ("SensorEntryID");


--
-- Name: Statistic_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Statistic"
    ADD CONSTRAINT "Statistic_pkey" PRIMARY KEY ("StatisticID");


--
-- Name: UserSetting_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "UserSetting"
    ADD CONSTRAINT "UserSetting_pkey" PRIMARY KEY ("Name");


--
-- Name: User_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "User"
    ADD CONSTRAINT "User_pkey" PRIMARY KEY ("UserID");


--
-- Name: Zone_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Zone"
    ADD CONSTRAINT "Zone_pkey" PRIMARY KEY ("ZoneID");


--
-- Name: actuator_timers; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER actuator_timers BEFORE UPDATE ON "Actuator" FOR EACH ROW EXECUTE PROCEDURE actuator_timers();

--
-- Name: condition_expressions; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER condition_expressions AFTER INSERT OR UPDATE ON "Condition" FOR EACH ROW EXECUTE PROCEDURE condition_expressions();


--
-- Name: Actuator_ZoneID_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Actuator"
    ADD CONSTRAINT "Actuator_ZoneID_fkey" FOREIGN KEY ("ZoneID") REFERENCES "Zone"("ZoneID");


--
-- Name: Sensor_MeasurandID_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Sensor"
    ADD CONSTRAINT "Sensor_MeasurandID_fkey" FOREIGN KEY ("MeasurandID") REFERENCES "Measurand"("MeasurandID");


--
-- Name: Sensor_ZoneID_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "Sensor"
    ADD CONSTRAINT "Sensor_ZoneID_fkey" FOREIGN KEY ("ZoneID") REFERENCES "Zone"("ZoneID");


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

